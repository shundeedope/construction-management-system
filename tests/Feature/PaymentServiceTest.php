<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentDirection;
use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Services\ActivityLogger;
use App\Services\PaymentService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->project = Project::factory()->create();
    $this->actor = assignToProject(userWithRole(Role::Accounting), $this->project, ProjectRole::ProjectManager, canViewFinancials: true);
    $this->invoice = Invoice::factory()->for($this->project)->for(Contract::factory()->for($this->project)->create(['client_id' => $this->project->client_id]))->approved()->create([
        'project_id' => $this->project->id,
        'total' => 1_000_00,
    ]);
    $this->service = app(PaymentService::class);
});

test('partial and final payments recalculate an invoice status', function () {
    $this->actingAs($this->actor);

    $this->service->record($this->invoice, $this->actor, paymentData(400_00));

    expect($this->invoice->fresh()->status)->toBe(InvoiceStatus::PartiallyPaid);

    $this->service->record($this->invoice, $this->actor, paymentData(600_00));

    expect($this->invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and(Payment::query()->sum('amount'))->toBe(1_000_00);
});

test('it rejects invalid payment amounts, direction, project, and overpayment', function (array $data) {
    expect(fn () => $this->service->record($this->invoice, $this->actor, $data))
        ->toThrow(ValidationException::class);
})->with([
    'zero amount' => fn (): array => paymentData(0),
    'negative amount' => fn (): array => paymentData(-1),
    'wrong direction' => fn (): array => paymentData(100_00, direction: PaymentDirection::Outgoing),
    'mismatched project' => fn (): array => paymentData(100_00, projectId: Project::factory()->create()->id),
    'overpayment' => fn (): array => paymentData(1_000_01),
]);

test('it prevents a later payment from exceeding the locked invoice balance', function () {
    $this->service->record($this->invoice, $this->actor, paymentData(600_00));

    expect(fn () => $this->service->record($this->invoice, $this->actor, paymentData(400_01)))
        ->toThrow(ValidationException::class);

    expect(Payment::query()->sum('amount'))->toBe(600_00);
});

test('removing a payment recalculates the invoice and logs both operations', function () {
    $this->actingAs($this->actor);
    $payment = $this->service->record($this->invoice, $this->actor, paymentData(1_000_00));

    $this->service->remove($payment, $this->actor);

    expect($payment->fresh()->trashed())->toBeTrue()
        ->and($this->invoice->fresh()->status)->toBe(InvoiceStatus::Approved)
        ->and(app(ActivityLogger::class)->queryFor($payment)->pluck('event')->all())
        ->toContain('payment_recorded', 'payment_removed');
});

test('removing the final payment restores a sent client invoice to sent', function () {
    $this->invoice->update(['status' => InvoiceStatus::Sent, 'sent_at' => now()]);
    $payment = $this->service->record($this->invoice, $this->actor, paymentData(1_000_00));

    $this->service->remove($payment, $this->actor);

    expect($this->invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

/** @return array{amount: int, project_id: int, direction: PaymentDirection, payment_date: string, method: string} */
function paymentData(int $amount, ?PaymentDirection $direction = null, ?int $projectId = null): array
{
    return [
        'amount' => $amount,
        'project_id' => $projectId ?? test()->project->id,
        'direction' => $direction ?? PaymentDirection::Incoming,
        'payment_date' => now()->toDateString(),
        'method' => 'wire',
    ];
}
