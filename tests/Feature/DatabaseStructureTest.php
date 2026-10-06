<?php

use App\Models\ChangeOrder;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\CompanySetting;
use App\Models\ComplianceRecord;
use App\Models\ComplianceRequirement;
use App\Models\Contract;
use App\Models\ContractLineItem;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\LeaveTransaction;
use App\Models\NumberSequence;
use App\Models\Payment;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('creates all domain and package-owned tables', function () {
    $tables = [
        'media', 'permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions', 'activity_log',
        'employees', 'clients', 'client_contacts', 'vendors', 'projects', 'project_user', 'contracts', 'contract_line_items',
        'change_orders', 'change_order_line_items', 'invoices', 'invoice_line_items', 'payments', 'documents', 'time_entries',
        'leave_transactions', 'compliance_requirements', 'compliance_records', 'number_sequences', 'company_settings',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Missing table: {$table}");
    }
});

it('uses integer cents and has the required structural columns', function () {
    expect(Schema::getColumnType('contracts', 'original_amount'))->toBe('integer')
        ->and(Schema::getColumnType('invoices', 'total'))->toBe('integer')
        ->and(Schema::getColumnType('payments', 'amount'))->toBe('integer');

    expect(Schema::hasColumns('projects', ['job_number', 'client_id', 'project_manager_id', 'status', 'deleted_at']))->toBeTrue()
        ->and(Schema::hasColumns('contracts', ['project_id', 'type', 'number', 'original_amount', 'status']))->toBeTrue()
        ->and(Schema::hasColumns('documents', ['project_id', 'documentable_type', 'documentable_id', 'category']))->toBeTrue();
});

it('persists the core model relationships through factories', function () {
    $client = Client::factory()->create();
    $contact = ClientContact::factory()->for($client)->create();
    $manager = User::factory()->create();
    $project = Project::factory()->for($client)->for($manager, 'projectManager')->create();
    $contract = Contract::factory()->for($project)->for($client)->create();
    $lineItem = ContractLineItem::factory()->for($contract)->create(['amount' => 5_000_00]);
    $employee = Employee::factory()->create();
    $timeEntry = TimeEntry::factory()->for($employee)->for($project)->create();

    expect($contact->client->is($client))->toBeTrue()
        ->and($project->client->is($client))->toBeTrue()
        ->and($project->projectManager->is($manager))->toBeTrue()
        ->and($contract->project->is($project))->toBeTrue()
        ->and($contract->lineItems->first()->is($lineItem))->toBeTrue()
        ->and($timeEntry->employee->is($employee))->toBeTrue();
});

it('supports media attachments and activity entries', function () {
    Storage::fake('public');

    $project = Project::factory()->create();
    $document = Document::factory()->for($project)->create();
    $media = $document->addMediaFromString('phase one document')
        ->usingFileName('phase-one.txt')
        ->toMediaCollection('documents', 'public');

    $activity = activity()
        ->performedOn($project)
        ->causedBy(User::factory()->create())
        ->withProperties(['phase' => 1])
        ->log('Project created');

    expect($document->getFirstMedia('documents')->is($media))->toBeTrue()
        ->and(Storage::disk('public')->exists($media->getPathRelativeToRoot()))->toBeTrue()
        ->and(Activity::query()->whereKey($activity->id)->value('subject_id'))->toBe($project->id);
});

it('supports roles and permissions without replacing the users table', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('view projects', 'web');
    $role = Role::findOrCreate('Phase One Role', 'web');
    $role->givePermissionTo('view projects');
    $user->assignRole($role);

    expect($user->hasRole('Phase One Role'))->toBeTrue()
        ->and($user->can('view projects'))->toBeTrue()
        ->and(Schema::hasTable('users'))->toBeTrue();
});

it('seeds the complete baseline scenario idempotently', function () {
    $counts = function (): array {
        return [
            'users' => User::count(), 'clients' => Client::count(), 'vendors' => Vendor::count(), 'projects' => Project::count(),
            'contracts' => Contract::count(), 'contract_line_items' => ContractLineItem::count(), 'invoices' => Invoice::count(),
            'payments' => Payment::count(), 'time_entries' => TimeEntry::count(), 'leave_transactions' => LeaveTransaction::count(),
            'compliance_requirements' => ComplianceRequirement::count(), 'compliance_records' => ComplianceRecord::count(),
            'number_sequences' => NumberSequence::count(), 'company_settings' => CompanySetting::count(),
            'documents' => Document::count(), 'change_orders' => ChangeOrder::count(), 'employees' => Employee::count(),
            'client_contacts' => ClientContact::count(),
        ];
    };

    $this->seed();
    $afterFirstSeed = $counts();

    // The Riverside scenario is the hand-written baseline the showcase portfolio is built
    // around; every other seeder must leave it exactly as FinancialRecordsSeeder wrote it.
    $riverside = Project::query()->where('job_number', 'JOB-2026-0042')->firstOrFail();

    expect(Contract::query()->whereBelongsTo($riverside)->count())->toBe(4)
        ->and(Invoice::query()->whereBelongsTo($riverside)->count())->toBe(4)
        ->and(Payment::query()->whereBelongsTo($riverside)->count())->toBe(4)
        ->and(ChangeOrder::query()->whereBelongsTo($riverside)->count())->toBe(3)
        ->and($afterFirstSeed['users'])->toBe(9)
        ->and($afterFirstSeed['projects'])->toBe(17);

    $this->seed();

    expect($counts())->toBe($afterFirstSeed);
});
