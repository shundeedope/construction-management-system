<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

class SubcontractorInvoiceWorkflowService
{
    public function __construct(
        private SubcontractorInvoiceCalculationService $calculations,
        private ActivityLogger $activityLogger,
        private WorkflowNotificationService $notifications,
    ) {}

    public function submit(Invoice $invoice, User $actor): Invoice
    {
        return $this->transition($invoice, $actor, InvoiceStatus::Submitted, 'submitted', function (Invoice $lockedInvoice): array {
            $this->calculations->syncLocked($lockedInvoice);

            return [];
        });
    }

    public function approve(Invoice $invoice, User $actor): Invoice
    {
        return $this->transition($invoice, $actor, InvoiceStatus::Approved, 'approved', fn (): array => [
            'approved_at' => now(),
            'approved_by' => $actor->getKey(),
        ]);
    }

    public function reject(Invoice $invoice, User $actor, string $reason): Invoice
    {
        if (blank($reason)) {
            throw ValidationException::withMessages([
                'rejection_reason' => 'A rejection reason is required.',
            ]);
        }

        return $this->transition($invoice, $actor, InvoiceStatus::Rejected, 'rejected', fn (): array => [
            'rejection_reason' => $reason,
        ]);
    }

    public function prepareForResubmission(Invoice $invoice, User $actor): Invoice
    {
        return $this->transition($invoice, $actor, InvoiceStatus::Draft, 'corrected_for_resubmission', fn (): array => []);
    }

    /** @param callable(Invoice): array<string, mixed> $attributes */
    private function transition(Invoice $invoice, User $actor, InvoiceStatus $to, string $event, callable $attributes): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor, $to, $event, $attributes): Invoice {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());
            Gate::forUser($actor)->authorize(match ($to) {
                InvoiceStatus::Submitted => 'submit',
                InvoiceStatus::Draft => 'resubmit',
                InvoiceStatus::Approved => 'approve',
                InvoiceStatus::Rejected => 'reject',
                default => throw new LogicException('Unsupported subcontractor-invoice transition.'),
            }, $lockedInvoice);

            if ($lockedInvoice->status === $to) {
                return $lockedInvoice;
            }

            if (! $lockedInvoice->status->canTransitionTo($to)) {
                throw new LogicException("The invoice cannot transition from [{$lockedInvoice->status->value}] to [{$to->value}].");
            }

            $from = $lockedInvoice->status;
            $lockedInvoice->update(['status' => $to, ...$attributes($lockedInvoice)]);
            $this->activityLogger->logTransition($lockedInvoice, $event, $from, $to);
            $this->notifications->subcontractorInvoiceTransitioned($lockedInvoice, $actor, $to);

            return $lockedInvoice->fresh();
        }, attempts: 3);
    }
}
