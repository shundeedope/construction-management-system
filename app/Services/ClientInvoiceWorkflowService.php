<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

class ClientInvoiceWorkflowService
{
    public function __construct(
        private InvoiceCalculationService $calculations,
        private ActivityLogger $activityLogger,
        private WorkflowNotificationService $notifications,
    ) {}

    public function approve(Invoice $invoice, User $actor): Invoice
    {
        return $this->transition($invoice, $actor, InvoiceStatus::Approved, 'approved', function (Invoice $lockedInvoice) use ($actor): array {
            if ($lockedInvoice->status !== InvoiceStatus::Draft) {
                throw new LogicException('Only draft client invoices can be approved.');
            }

            $this->calculations->syncLocked($lockedInvoice);

            return [
                'approved_at' => now(),
                'approved_by' => $actor->getKey(),
            ];
        });
    }

    public function send(Invoice $invoice, User $actor): Invoice
    {
        return $this->transition($invoice, $actor, InvoiceStatus::Sent, 'sent', fn (): array => ['sent_at' => now()]);
    }

    public function void(Invoice $invoice, User $actor, string $reason): Invoice
    {
        if (blank($reason)) {
            throw ValidationException::withMessages([
                'void_reason' => 'A void reason is required.',
            ]);
        }

        return $this->transition($invoice, $actor, InvoiceStatus::Void, 'voided', fn (): array => [
            'voided_at' => now(),
            'void_reason' => $reason,
        ]);
    }

    /**
     * @param  callable(Invoice): array<string, mixed>  $attributes
     */
    private function transition(Invoice $invoice, User $actor, InvoiceStatus $to, string $event, callable $attributes): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor, $to, $event, $attributes): Invoice {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());
            Gate::forUser($actor)->authorize(match ($to) {
                InvoiceStatus::Approved => 'approve',
                InvoiceStatus::Sent => 'send',
                InvoiceStatus::Void => 'void',
                default => throw new LogicException('Unsupported client-invoice transition.'),
            }, $lockedInvoice);

            if ($lockedInvoice->status === $to) {
                return $lockedInvoice;
            }

            if (! $lockedInvoice->status->canTransitionTo($to)) {
                throw new LogicException("The invoice cannot transition from [{$lockedInvoice->status->value}] to [{$to->value}].");
            }

            $from = $lockedInvoice->status;
            $lockedInvoice->update([
                'status' => $to,
                ...$attributes($lockedInvoice),
            ]);
            $this->activityLogger->logTransition($lockedInvoice, $event, $from, $to);
            $this->notifications->clientInvoiceTransitioned($lockedInvoice, $actor, $to);

            return $lockedInvoice->fresh();
        }, attempts: 3);
    }
}
