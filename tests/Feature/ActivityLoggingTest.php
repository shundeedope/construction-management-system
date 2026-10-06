<?php

use App\Enums\CompanySettingType;
use App\Enums\ContractStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ProjectStatus;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CompanySettings;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->logger = app(ActivityLogger::class);
    $this->actor = User::factory()->create();
    $this->project = Project::factory()->create();

    $this->actingAs($this->actor);
});

it('records one attributable entry for an important transition', function () {
    $contract = Contract::factory()->for($this->project)->create(['status' => ContractStatus::PendingApproval]);

    $activity = $this->logger->logTransition($contract, 'executed', ContractStatus::PendingApproval, ContractStatus::Executed);

    expect($activity)->toBeInstanceOf(Activity::class)
        ->and($activity->event)->toBe('executed')
        ->and($activity->causer_id)->toBe($this->actor->id)
        ->and($activity->subject_id)->toBe($contract->id)
        ->and($activity->subject_type)->toBe($contract->getMorphClass())
        ->and($activity->properties['old']['status'])->toBe('pending_approval')
        ->and($activity->properties['attributes']['status'])->toBe('executed')
        ->and($activity->properties['project_id'])->toBe($this->project->id);

    expect($this->logger->queryFor($contract)->where('event', 'executed')->count())->toBe(1);
});

it('does not duplicate a transition log when an idempotent retry re-applies the same state', function () {
    $invoice = Invoice::factory()->for($this->project)->create(['status' => InvoiceStatus::Approved]);

    $first = $this->logger->logTransition($invoice, 'approved', InvoiceStatus::Submitted, InvoiceStatus::Approved);

    // The action is retried, but the invoice is already approved: from and to now match.
    $retry = $this->logger->logTransition($invoice, 'approved', InvoiceStatus::Approved, InvoiceStatus::Approved);

    expect($first)->not->toBeNull()
        ->and($retry)->toBeNull()
        ->and($this->logger->queryFor($invoice)->where('event', 'approved')->count())->toBe(1);
});

it('records the project context for financial actions that are not transitions', function () {
    $invoice = Invoice::factory()->for($this->project)->create(['status' => InvoiceStatus::Sent]);
    $payment = Payment::factory()->for($this->project)->for($invoice)->create(['amount' => 25_000_00]);

    $activity = $this->logger->log($payment, 'payment_recorded', ['amount' => $payment->amount]);

    expect($activity->event)->toBe('payment_recorded')
        ->and($activity->properties['amount'])->toBe(25_000_00)
        ->and($activity->properties['project_id'])->toBe($this->project->id)
        ->and($activity->causer_id)->toBe($this->actor->id);
});

it('logs a system action with no causer when nobody is authenticated', function () {
    auth()->logout();

    $activity = $this->logger->log($this->project, 'imported');

    expect($activity->causer_id)->toBeNull()
        ->and($activity->event)->toBe('imported');
});

it('records model changes for domain records', function () {
    $contract = Contract::factory()->for($this->project)->create(['status' => ContractStatus::Draft]);

    $contract->update(['status' => ContractStatus::PendingApproval]);

    $activity = Activity::query()
        ->where('subject_type', $contract->getMorphClass())
        ->where('subject_id', $contract->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    // Model-level changes are stored in `attribute_changes`; workflow transitions logged
    // through ActivityLogger put the same old/new shape in `properties`.
    expect($activity)->not->toBeNull()
        ->and($activity->attribute_changes['attributes']['status'])->toBe('pending_approval')
        ->and($activity->attribute_changes['old']['status'])->toBe('draft');
});

it('does not log an update that changed nothing of interest', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Active]);

    $before = Activity::query()->count();

    // internal_notes is not an audited attribute, so it must not create an entry.
    $project->update(['internal_notes' => 'Reminder to call the client.']);

    expect(Activity::query()->count())->toBe($before);
});

it('never writes a sensitive setting value to the activity log', function () {
    $settings = app(CompanySettings::class);

    $setting = $settings->set('google_drive_client_secret', 'super-secret-token', CompanySettingType::Encrypted);

    $setting->update(['description' => 'Drive credentials']);

    $entries = Activity::query()
        ->where('subject_type', $setting->getMorphClass())
        ->where('subject_id', $setting->id)
        ->get();

    expect($entries)->not->toBeEmpty();

    foreach ($entries as $entry) {
        // Scan both columns: `attribute_changes` is where a logged column value would land.
        $serialized = json_encode([$entry->properties, $entry->attribute_changes]);

        expect($serialized)->not->toContain('super-secret-token')
            ->and($serialized)->not->toContain($setting->value)
            ->and(array_keys($entry->attribute_changes?->get('attributes') ?? []))->not->toContain('value');
    }

    // The value itself is still stored encrypted and readable only through the settings service.
    expect($setting->value)->not->toBe('super-secret-token')
        ->and($settings->get('google_drive_client_secret'))->toBe('super-secret-token');
});

it('never logs user credentials', function () {
    $this->actor->update(['name' => 'Renamed User']);

    $entries = Activity::query()
        ->where('subject_type', $this->actor->getMorphClass())
        ->get();

    foreach ($entries as $entry) {
        expect(json_encode([$entry->properties, $entry->attribute_changes]))->not->toContain('password');
    }
})->throwsNoExceptions();
