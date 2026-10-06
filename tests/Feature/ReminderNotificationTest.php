<?php

use App\Enums\ContractStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Project;
use App\Notifications\WorkflowReminder;
use App\Services\WorkflowReminderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('overdue invoice reminders are sent to authorized financial users once per day', function () {
    Notification::fake();
    $project = Project::factory()->create();
    $accounting = userWithRole(Role::Accounting);
    $manager = assignToProject(userWithRole(Role::ProjectManager), $project, ProjectRole::ProjectManager, canViewFinancials: true);
    $contract = Contract::factory()->for($project)->create(['client_id' => $project->client_id, 'status' => ContractStatus::Executed]);
    $invoice = Invoice::factory()->for($project)->for($contract)->create(['status' => InvoiceStatus::Sent, 'due_date' => today()->subDay()]);

    app(WorkflowReminderService::class)->send();

    Notification::assertSentTo([$accounting, $manager], WorkflowReminder::class, fn (WorkflowReminder $notification): bool => $notification->key === "overdue-invoice:{$invoice->getKey()}:".today()->toDateString());

    DatabaseNotification::query()->create([
        'id' => (string) str()->uuid(),
        'type' => WorkflowReminder::class,
        'notifiable_type' => $manager->getMorphClass(),
        'notifiable_id' => $manager->getKey(),
        'data' => ['key' => "overdue-invoice:{$invoice->getKey()}:".today()->toDateString()],
    ]);
    Notification::fake();

    app(WorkflowReminderService::class)->send();

    Notification::assertNotSentTo($manager, WorkflowReminder::class);
});

test('rejected invoice reminders are sent to the invoice creator only once', function () {
    Notification::fake();
    $project = Project::factory()->create();
    $creator = userWithRole(Role::Accounting);
    $contract = Contract::factory()->for($project)->create(['client_id' => $project->client_id, 'status' => ContractStatus::Executed]);
    $invoice = Invoice::factory()->for($project)->for($contract)->create(['created_by' => $creator->getKey(), 'status' => InvoiceStatus::Rejected]);

    app(WorkflowReminderService::class)->send();
    Notification::assertSentTo($creator, WorkflowReminder::class, fn (WorkflowReminder $notification): bool => $notification->key === "invoice-rejected:{$invoice->getKey()}");

    DatabaseNotification::query()->create([
        'id' => (string) str()->uuid(),
        'type' => WorkflowReminder::class,
        'notifiable_type' => $creator->getMorphClass(),
        'notifiable_id' => $creator->getKey(),
        'data' => ['key' => "invoice-rejected:{$invoice->getKey()}"],
    ]);
    Notification::fake();

    app(WorkflowReminderService::class)->send();

    Notification::assertNotSentTo($creator, WorkflowReminder::class);
});
