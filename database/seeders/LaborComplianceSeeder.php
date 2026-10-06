<?php

namespace Database\Seeders;

use App\Models\ComplianceRecord;
use App\Models\ComplianceRequirement;
use App\Models\Employee;
use App\Models\LeaveTransaction;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class LaborComplianceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $project = Project::query()->where('job_number', 'JOB-2026-0042')->firstOrFail();
        $employee = Employee::query()->where('employee_number', 'EMP-0001')->firstOrFail();
        $supervisor = User::query()->where('email', 'supervisor@example.com')->firstOrFail();
        $vendor = Vendor::query()->where('company_name', 'Northstar Electrical')->firstOrFail();

        foreach ([
            ['2026-03-10', 'Site preparation'],
            ['2026-03-11', 'Material staging'],
            ['2026-03-12', 'Safety walkthrough'],
        ] as [$workDate, $description]) {
            $timeEntry = TimeEntry::query()
                ->where('employee_id', $employee->id)
                ->where('project_id', $project->id)
                ->whereDate('work_date', $workDate)
                ->first() ?? new TimeEntry([
                    'employee_id' => $employee->id,
                    'project_id' => $project->id,
                    'work_date' => $workDate,
                ]);
            $timeEntry->fill([
                'regular_hours' => 8, 'overtime_hours' => 0, 'is_billable' => true, 'description' => $description,
                'status' => 'submitted', 'submitted_at' => now()->subDay(),
            ])->save();
        }

        // `effective_date` is a date cast: a firstOrCreate keyed on it compares '2026-01-01'
        // against the stored '2026-01-01 00:00:00' and never matches, so re-seeding would add
        // the accrual a second time. Match it as a date instead.
        $openingAccrual = LeaveTransaction::query()
            ->where('employee_id', $employee->id)
            ->where('type', 'accrual')
            ->whereDate('effective_date', '2026-01-01')
            ->exists();

        if (! $openingAccrual) {
            LeaveTransaction::query()->create([
                'employee_id' => $employee->id, 'type' => 'accrual', 'effective_date' => '2026-01-01',
                'hours' => 40, 'created_by' => $supervisor->id, 'notes' => 'Annual opening accrual',
            ]);
        }

        $safetyRequirement = ComplianceRequirement::query()->firstOrCreate(['name' => 'Safety training'], [
            'applies_to' => 'employee', 'is_required' => true, 'default_reminder_days' => 30, 'is_active' => true,
        ]);
        $insuranceRequirement = ComplianceRequirement::query()->firstOrCreate(['name' => 'Vendor insurance certificate'], [
            'applies_to' => 'vendor', 'is_required' => true, 'default_reminder_days' => 30, 'is_active' => true,
        ]);

        ComplianceRecord::query()->updateOrCreate([
            'compliance_requirement_id' => $safetyRequirement->id, 'employee_id' => $employee->id,
        ], [
            'issued_at' => now()->subMonths(3)->toDateString(), 'expires_at' => now()->addDays(20)->toDateString(), 'status' => 'expiring_soon',
            'verified_by' => $supervisor->id, 'verified_at' => now()->subMonths(3),
        ]);

        ComplianceRecord::query()->updateOrCreate([
            'compliance_requirement_id' => $insuranceRequirement->id, 'vendor_id' => $vendor->id,
        ], [
            'issued_at' => now()->subYear()->toDateString(), 'expires_at' => now()->subDay()->toDateString(), 'status' => 'expired',
        ]);
    }
}
