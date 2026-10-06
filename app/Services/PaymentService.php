<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentDirection;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private ActivityLogger $activityLogger) {}

    /**
     * @param  array{amount: int, project_id?: int, direction?: PaymentDirection|string, payment_date: string, method?: string|null, reference?: string|null, notes?: string|null}  $attributes
     */
    public function record(Invoice $invoice, User $actor, array $attributes): Payment
    {
        return DB::transaction(function () use ($invoice, $actor, $attributes): Payment {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());
            Gate::forUser($actor)->authorize('create', Payment::class);
            Gate::forUser($actor)->authorize('view', $lockedInvoice);

            $this->validatePayment($lockedInvoice, $attributes);
            $amount = (int) $attributes['amount'];
            $paidAmount = (int) $lockedInvoice->payments()->sum('amount');

            if ($paidAmount + $amount > $lockedInvoice->total) {
                throw ValidationException::withMessages([
                    'amount' => 'The payment exceeds the remaining invoice balance.',
                ]);
            }

            $payment = $lockedInvoice->payments()->create([
                'project_id' => $lockedInvoice->project_id,
                'direction' => $lockedInvoice->type->paymentDirection(),
                'amount' => $amount,
                'payment_date' => $attributes['payment_date'],
                'method' => $attributes['method'] ?? null,
                'reference' => $attributes['reference'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'recorded_by' => $actor->getKey(),
            ]);

            $this->recalculateStatus($lockedInvoice, $paidAmount + $amount);
            $this->activityLogger->log($payment, 'payment_recorded', ['amount' => $payment->amount]);

            return $payment;
        }, attempts: 3);
    }

    public function remove(Payment $payment, User $actor): Payment
    {
        return DB::transaction(function () use ($payment, $actor): Payment {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);
            /** @var Payment $lockedPayment */
            $lockedPayment = $lockedInvoice->payments()->findOrFail($payment->getKey());
            Gate::forUser($actor)->authorize('delete', $lockedPayment);

            $lockedPayment->delete();
            $paidAmount = (int) $lockedInvoice->payments()->sum('amount');
            $this->recalculateStatus($lockedInvoice, $paidAmount);
            $this->activityLogger->log($lockedPayment, 'payment_removed', ['amount' => $lockedPayment->amount]);

            return $lockedPayment;
        }, attempts: 3);
    }

    /** @param array{amount: int, project_id?: int, direction?: PaymentDirection|string, payment_date: string, method?: string|null, reference?: string|null, notes?: string|null} $attributes */
    private function validatePayment(Invoice $invoice, array $attributes): void
    {
        $errors = [];
        $amount = $attributes['amount'];
        $direction = $attributes['direction'] ?? $invoice->type->paymentDirection();
        $direction = $direction instanceof PaymentDirection ? $direction : PaymentDirection::tryFrom((string) $direction);

        if ($amount <= 0) {
            $errors['amount'] = 'The payment amount must be a positive integer number of cents.';
        }

        if (($attributes['project_id'] ?? $invoice->project_id) !== $invoice->project_id) {
            $errors['project_id'] = 'The payment project must match the invoice project.';
        }

        if ($direction !== $invoice->type->paymentDirection()) {
            $errors['direction'] = 'The payment direction must match the invoice type.';
        }

        if (! $invoice->status->acceptsPayments()) {
            $errors['invoice'] = 'Payments can only be recorded against approved, sent, or partially paid invoices.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function recalculateStatus(Invoice $invoice, int $paidAmount): void
    {
        $status = match (true) {
            $paidAmount >= $invoice->total => InvoiceStatus::Paid,
            $paidAmount > 0 => InvoiceStatus::PartiallyPaid,
            $invoice->type->isReceivable() && $invoice->sent_at !== null => InvoiceStatus::Sent,
            default => InvoiceStatus::Approved,
        };

        if ($invoice->status !== $status) {
            $invoice->update(['status' => $status]);
        }
    }
}
