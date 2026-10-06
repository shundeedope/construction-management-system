<?php

namespace Database\Seeders;

use App\Enums\ComplianceSubject;
use App\Enums\LeaveTransactionType;
use App\Enums\ProjectStatus;
use App\Enums\TimeEntryStatus;
use App\Models\ComplianceRecord;
use App\Models\ComplianceRequirement;
use App\Models\Employee;
use App\Models\LeaveTransaction;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ComplianceStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * The people side of the showcase: twelve weeks of timesheets, a year of leave accruals, and a
 * compliance wall with certificates in every state — valid, expiring, expired, and missing.
 */
class ShowcaseWorkforceSeeder extends Seeder
{
    /**
     * Weeks of timesheet history. Entries age from draft (this week) through submitted (the
     * two weeks awaiting approval) to approved, with a few rejections in the mix.
     */
    private const int TIMESHEET_WEEKS = 12;

    /**
     * @var array<int, array{name: string, applies_to: ComplianceSubject, reminder_days: int, description: string}>
     */
    private const array REQUIREMENTS = [
        ['name' => 'OSHA 30 Safety Certification', 'applies_to' => ComplianceSubject::Employee, 'reminder_days' => 45, 'description' => 'Required for all site personnel before badge issuance.'],
        ['name' => 'First Aid & CPR', 'applies_to' => ComplianceSubject::Employee, 'reminder_days' => 30, 'description' => 'At least one certified responder must be on site at all times.'],
        ['name' => 'Forklift Operator Certification', 'applies_to' => ComplianceSubject::Employee, 'reminder_days' => 30, 'description' => 'Required to operate powered industrial trucks.'],
        ['name' => 'General Liability Insurance', 'applies_to' => ComplianceSubject::Vendor, 'reminder_days' => 30, 'description' => 'Certificate of insurance naming Summit GC as additional insured.'],
        ['name' => "Workers' Compensation Certificate", 'applies_to' => ComplianceSubject::Vendor, 'reminder_days' => 30, 'description' => 'Statutory coverage for all trade personnel on site.'],
        ['name' => 'W-9 Tax Form', 'applies_to' => ComplianceSubject::Vendor, 'reminder_days' => 60, 'description' => 'Required before the first payment is released.'],
        ['name' => 'Building Permit', 'applies_to' => ComplianceSubject::Project, 'reminder_days' => 60, 'description' => 'Issued by the authority having jurisdiction.'],
        ['name' => 'Stormwater Discharge Permit', 'applies_to' => ComplianceSubject::Project, 'reminder_days' => 45, 'description' => 'Required for any site disturbing more than one acre.'],
    ];

    /**
     * Site work descriptions, cycled through so timesheets read like a real job log.
     *
     * @var array<int, string>
     */
    private const array WORK_LOG = [
        'Formwork and rebar placement',
        'Concrete pour and finishing',
        'Structural steel erection',
        'Rough-in electrical',
        'Mechanical duct installation',
        'Drywall hanging and taping',
        'Interior painting',
        'Site cleanup and material staging',
        'Safety walkthrough and toolbox talk',
        'Punch list corrections',
        'Equipment maintenance',
        'Owner progress meeting',
    ];

    public function __construct(private ComplianceStatusService $complianceStatus) {}

    public function run(): void
    {
        fake()->seed(20260713);

        $supervisor = User::query()->where('email', 'supervisor@example.com')->firstOrFail();
        $employees = Employee::query()->where('is_active', true)->orderBy('id')->get();
        $projects = Project::query()
            ->whereIn('status', [ProjectStatus::Active, ProjectStatus::OnHold])
            ->orderBy('id')
            ->get();

        if ($projects->isEmpty() || $employees->isEmpty()) {
            return;
        }

        $this->seedTimeEntries($employees, $projects, $supervisor);
        $this->seedLeave($employees, $supervisor);
        $this->seedCompliance($supervisor);
    }

    /**
     * Each employee works a home project, plus a second one on Fridays so the timesheet grid
     * and the project labor reports both have something to show.
     *
     * @param  Collection<int, Employee>  $employees
     * @param  Collection<int, Project>  $projects
     */
    private function seedTimeEntries(Collection $employees, Collection $projects, User $supervisor): void
    {
        $startOfWeek = CarbonImmutable::now()->startOfWeek();

        foreach ($employees as $employeeIndex => $employee) {
            $homeProject = $projects[$employeeIndex % $projects->count()];
            $secondaryProject = $projects[($employeeIndex + 1) % $projects->count()];

            for ($week = self::TIMESHEET_WEEKS - 1; $week >= 0; $week--) {
                $weekStart = $startOfWeek->subWeeks($week);

                for ($day = 0; $day < 5; $day++) {
                    $workDate = $weekStart->addDays($day);

                    if ($workDate->isFuture()) {
                        continue;
                    }

                    $project = $day === 4 ? $secondaryProject : $homeProject;
                    $status = $this->timeEntryStatus($week, $employeeIndex + $day);
                    $overtime = $day === 3 && $employeeIndex % 3 === 0 ? 2 : 0;

                    // `work_date` is a date cast, so a firstOrCreate on it would compare
                    // '2026-04-27' against a stored '2026-04-27 00:00:00' and never match,
                    // then trip the unique index on the second run. Look it up as a date.
                    $exists = TimeEntry::query()
                        ->where('employee_id', $employee->id)
                        ->where('project_id', $project->id)
                        ->whereDate('work_date', $workDate->toDateString())
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    TimeEntry::query()->create([
                        'employee_id' => $employee->id,
                        'project_id' => $project->id,
                        'work_date' => $workDate->toDateString(),
                        'regular_hours' => 8,
                        'overtime_hours' => $overtime,
                        'is_billable' => $day !== 4 || $employeeIndex % 4 !== 0,
                        'description' => self::WORK_LOG[($employeeIndex + $day + $week) % count(self::WORK_LOG)],
                        'status' => $status,
                        'submitted_at' => $status === TimeEntryStatus::Draft ? null : $weekStart->addDays(5),
                        'approved_at' => $status === TimeEntryStatus::Approved ? $weekStart->addDays(6) : null,
                        'approved_by' => $status === TimeEntryStatus::Approved ? $supervisor->id : null,
                        'rejection_reason' => $status === TimeEntryStatus::Rejected ? 'Hours do not match the daily site log; please resubmit.' : null,
                    ]);
                }
            }
        }
    }

    /**
     * `$weeksAgo` is 0 for the current week. Anything older than the two open weeks has been
     * through approval already.
     */
    private function timeEntryStatus(int $weeksAgo, int $variation): TimeEntryStatus
    {
        return match (true) {
            $weeksAgo === 0 => TimeEntryStatus::Draft,
            $weeksAgo <= 2 => TimeEntryStatus::Submitted,
            $variation % 17 === 0 => TimeEntryStatus::Rejected,
            default => TimeEntryStatus::Approved,
        };
    }

    /**
     * @param  Collection<int, Employee>  $employees
     */
    private function seedLeave(Collection $employees, User $supervisor): void
    {
        foreach ($employees as $index => $employee) {
            for ($monthsAgo = 11; $monthsAgo >= 0; $monthsAgo--) {
                $effectiveDate = CarbonImmutable::now()->startOfMonth()->subMonths($monthsAgo);

                if ($employee->hired_at?->greaterThan($effectiveDate) === true) {
                    continue;
                }

                $this->leave($employee, LeaveTransactionType::Accrual, $effectiveDate, 8, 'Monthly PTO accrual', $supervisor);

                // Roughly every third employee takes time off in any given quarter.
                if (($index + $monthsAgo) % 7 === 0) {
                    $this->leave(
                        $employee,
                        LeaveTransactionType::Usage,
                        $effectiveDate->addDays(14),
                        -16,
                        fake()->randomElement(['Vacation', 'Family leave', 'Personal day']),
                        $supervisor,
                    );
                }
            }

            if ($index % 5 === 0) {
                $this->leave(
                    $employee,
                    LeaveTransactionType::Adjustment,
                    CarbonImmutable::now()->subMonths(2),
                    4,
                    'Carry-over correction from prior year',
                    $supervisor,
                );
            }
        }
    }

    /**
     * `effective_date` is a date cast, so it has to be matched with whereDate for the seeder
     * to stay idempotent — see the note on the timesheet loop.
     */
    private function leave(Employee $employee, LeaveTransactionType $type, CarbonImmutable $effectiveDate, int $hours, string $notes, User $supervisor): void
    {
        $exists = LeaveTransaction::query()
            ->where('employee_id', $employee->id)
            ->where('type', $type)
            ->whereDate('effective_date', $effectiveDate->toDateString())
            ->exists();

        if ($exists) {
            return;
        }

        LeaveTransaction::query()->create([
            'employee_id' => $employee->id,
            'type' => $type,
            'effective_date' => $effectiveDate->toDateString(),
            'hours' => $hours,
            'notes' => $notes,
            'created_by' => $supervisor->id,
        ]);
    }

    /**
     * Certificates land on a spread of expiry dates so the compliance board shows every state
     * at once. A vendor's general liability record is pinned to the same date as the vendor's
     * own `insurance_expires_at` column, so the two never contradict each other on screen.
     */
    private function seedCompliance(User $supervisor): void
    {
        $requirements = collect(self::REQUIREMENTS)->mapWithKeys(fn (array $requirement): array => [
            $requirement['name'] => ComplianceRequirement::query()->updateOrCreate(['name' => $requirement['name']], [
                'applies_to' => $requirement['applies_to'],
                'description' => $requirement['description'],
                'is_required' => true,
                'default_reminder_days' => $requirement['reminder_days'],
                'is_active' => true,
            ]),
        ]);

        // Days from today until each certificate lapses. Negatives are already expired, and a
        // null means the certificate was never collected at all.
        $employeeExpiries = [412, 265, -21, 19, 380, 91, -7, 44, 610, 12, 208, 340, null, 155, 63, -60];

        foreach (Employee::query()->orderBy('id')->get() as $index => $employee) {
            foreach (['OSHA 30 Safety Certification', 'First Aid & CPR', 'Forklift Operator Certification'] as $offset => $name) {
                if ($name === 'Forklift Operator Certification' && $index % 3 !== 0) {
                    continue;
                }

                $expiresIn = $employeeExpiries[($index * 2 + $offset) % count($employeeExpiries)];

                $this->record($requirements[$name], ['employee_id' => $employee->id], $expiresIn, $supervisor);
            }
        }

        foreach (Vendor::query()->orderBy('id')->get() as $index => $vendor) {
            $this->record(
                $requirements['General Liability Insurance'],
                ['vendor_id' => $vendor->id],
                $vendor->insurance_expires_at === null ? null : (int) today()->diffInDays($vendor->insurance_expires_at, absolute: false),
                $supervisor,
            );

            $this->record($requirements["Workers' Compensation Certificate"], ['vendor_id' => $vendor->id], [287, 34, -15, 122, 9, 401][$index % 6], $supervisor);
            $this->record($requirements['W-9 Tax Form'], ['vendor_id' => $vendor->id], $index % 4 === 0 ? null : 900, $supervisor);
        }

        $projects = Project::query()->whereIn('status', [ProjectStatus::Active, ProjectStatus::OnHold, ProjectStatus::Planning])->orderBy('id')->get();

        foreach ($projects as $index => $project) {
            $this->record($requirements['Building Permit'], ['project_id' => $project->id], [540, 87, 22, -9, 310][$index % 5], $supervisor);
            $this->record($requirements['Stormwater Discharge Permit'], ['project_id' => $project->id], $index % 3 === 0 ? 41 : 260, $supervisor);
        }
    }

    /**
     * @param  array<string, int>  $subject
     */
    private function record(ComplianceRequirement $requirement, array $subject, ?int $expiresInDays, User $supervisor): void
    {
        $expiresAt = $expiresInDays === null ? null : today()->addDays($expiresInDays);

        $record = ComplianceRecord::query()->updateOrCreate([
            'compliance_requirement_id' => $requirement->id,
            ...$subject,
        ], [
            'issued_at' => $expiresAt?->copy()->subYear()->toDateString(),
            'expires_at' => $expiresAt?->toDateString(),
            'status' => 'valid',
            'notes' => $expiresAt === null ? 'Certificate has not been provided yet.' : null,
            'verified_by' => $expiresAt === null ? null : $supervisor->id,
            'verified_at' => $expiresAt === null ? null : now()->subMonths(2),
        ]);

        $this->complianceStatus->refresh($record);
    }
}
