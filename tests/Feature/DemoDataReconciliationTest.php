<?php

use App\Enums\ChangeOrderStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\Role;
use App\Enums\TimeEntryStatus;
use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Vendor;
use App\Services\DashboardMetricsService;
use App\Services\ProjectFinancialSummaryService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('the Riverside showcase scenario reconciles its contracts, changes, invoices, and payments', function () {
    $project = Project::query()->where('job_number', 'JOB-2026-0042')->firstOrFail();
    $ownerContract = Contract::query()->where('number', 'OC-JOB-2026-0042-0001')->firstOrFail();

    expect($project->name)->toBe('Riverside Office Renovation')
        ->and($ownerContract->original_amount)->toBe(500_000_00)
        ->and($ownerContract->lineItems()->sum('amount'))->toBe(500_000_00)
        ->and(Contract::query()->whereBelongsTo($project)->where('type', 'subcontract')->sum('original_amount'))->toBe(185_000_00)
        ->and(ChangeOrder::query()->whereBelongsTo($project)->where('status', ChangeOrderStatus::Approved)->where('type', 'owner')->firstOrFail()->lineItems()->sum('amount'))->toBe(15_000_00)
        ->and(ChangeOrder::query()->whereBelongsTo($project)->where('status', ChangeOrderStatus::Approved)->where('type', 'subcontract')->firstOrFail()->lineItems()->sum('amount'))->toBe(10_000_00)
        ->and(ChangeOrder::query()->whereBelongsTo($project)->where('status', ChangeOrderStatus::Submitted)->where('title', 'Additional lighting')->exists())->toBeTrue()
        ->and(Invoice::query()->whereBelongsTo($project)->where('type', 'client')->sum('subtotal'))->toBe(300_000_00)
        ->and(Invoice::query()->whereBelongsTo($project)->where('type', 'subcontractor')->sum('subtotal'))->toBe(180_000_00)
        ->and($project->payments()->where('direction', 'incoming')->sum('amount'))->toBe(250_000_00)
        ->and($project->payments()->where('direction', 'outgoing')->sum('amount'))->toBe(150_000_00);

    $summary = app(ProjectFinancialSummaryService::class)->summary($project);

    expect($summary['revised_owner_value']->cents)->toBe(515_000_00)
        ->and($summary['revised_commitments']->cents)->toBe(195_000_00)
        ->and($summary['gross_margin']->cents)->toBe(320_000_00)
        ->and($summary['gross_margin_percentage'])->toBe(62.14)
        ->and($summary['client_invoiced']->cents)->toBe(300_000_00)
        ->and($summary['subcontractor_invoiced']->cents)->toBe(180_000_00)
        ->and($summary['received']->cents)->toBe(250_000_00)
        ->and($summary['paid']->cents)->toBe(150_000_00);
});

test('the Riverside scenario still raises its own attention items inside the wider portfolio', function () {
    $riverside = Project::query()->where('job_number', 'JOB-2026-0042')->firstOrFail();

    expect(Invoice::query()->where('number', 'INV-JOB-2026-0042-0001')->value('status'))->toBe(InvoiceStatus::PartiallyPaid)
        ->and(Vendor::query()->where('company_name', 'Northstar Electrical')->firstOrFail()->insurance_expires_at?->toDateString())->toBe(today()->subDay()->toDateString())
        ->and(Invoice::query()->whereBelongsTo($riverside)->where('type', InvoiceType::Client)->whereDate('due_date', '<', today())->whereIn('status', ['approved', 'sent', 'partially_paid'])->count())->toBe(1)
        ->and(ChangeOrder::query()->whereBelongsTo($riverside)->where('status', ChangeOrderStatus::Submitted)->count())->toBe(1)
        ->and(TimeEntry::query()
            ->whereBelongsTo($riverside)
            ->where('status', TimeEntryStatus::Submitted)
            ->whereDate('work_date', '>=', '2026-03-10')
            ->whereDate('work_date', '<=', '2026-03-12')
            ->count())->toBe(3);
});

test('the dashboard reports the whole seeded portfolio, not just the baseline project', function () {
    $administrator = User::query()->where('email', 'admin@example.com')->firstOrFail();
    $metrics = app(DashboardMetricsService::class);
    $financial = $metrics->financial($administrator);
    $attention = $metrics->attention($administrator);

    // Contract-derived figures are exact: they come from fixed contract values plus the fixed
    // set of approved change orders, and the Riverside numbers are folded into both.
    expect($financial['active_projects'])->toBe(8)
        ->and($financial['revised_value']->cents)->toBe(110_145_450_00)
        ->and($financial['margin']->cents)->toBe(66_358_350_00)
        ->and($financial['receivables']->cents)->toBeGreaterThan(515_000_00)
        ->and($financial['payables']->cents)->toBeGreaterThan(30_000_00);

    // Every queue a reviewer opens the panel to clear has something waiting in it. The exact
    // counts move with the calendar, so only their presence is asserted here.
    expect($attention['overdue_invoices'])->toBeGreaterThan(1)
        ->and($attention['pending_changes'])->toBeGreaterThan(1)
        ->and($attention['pending_timesheets'])->toBeGreaterThan(3)
        ->and($attention['expiring_compliance'])->toBeGreaterThan(2);
});

test('each showcase user can authenticate with the documented demo password and role', function (string $email, Role $role, bool $isAssignedToRiverside) {
    $user = User::query()->where('email', $email)->firstOrFail();

    expect(Hash::check('password', $user->password))->toBeTrue()
        ->and($user->hasRole($role->value))->toBeTrue()
        ->and($user->projects()->where('job_number', 'JOB-2026-0042')->exists())->toBe($isAssignedToRiverside);
})->with([
    'administrator' => ['admin@example.com', Role::Administrator, false],
    'project manager' => ['manager@example.com', Role::ProjectManager, true],
    'accounting' => ['accounting@example.com', Role::Accounting, false],
    'site supervisor' => ['supervisor@example.com', Role::SiteSupervisor, true],
    'employee' => ['employee@example.com', Role::Employee, true],
    'executive' => ['executive@example.com', Role::Executive, false],
]);
