<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InvoiceStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Sent = 'sent';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Rejected = 'rejected';
    case Void = 'void';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Sent => 'Sent',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
            self::Rejected => 'Rejected',
            self::Void => 'Void',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'warning',
            self::Approved => 'info',
            self::Sent => 'info',
            self::PartiallyPaid => 'warning',
            self::Paid => 'success',
            self::Rejected, self::Void => 'danger',
        };
    }

    /**
     * Only approved, non-void invoices contribute to billed and cost totals.
     */
    public function countsTowardsProjectFinancials(): bool
    {
        return in_array($this, [self::Approved, self::Sent, self::PartiallyPaid, self::Paid], strict: true);
    }

    /**
     * Amounts and progress-billing lines are frozen once an invoice leaves draft.
     */
    public function isFinancesLocked(): bool
    {
        return $this !== self::Draft;
    }

    public function acceptsPayments(): bool
    {
        return in_array($this, [self::Approved, self::Sent, self::PartiallyPaid], strict: true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Approved, self::Void],
            self::Submitted => [self::Approved, self::Rejected, self::Void],
            self::Approved => [self::Sent, self::PartiallyPaid, self::Paid, self::Void],
            self::Sent => [self::PartiallyPaid, self::Paid, self::Void],
            self::PartiallyPaid => [self::Paid, self::Sent, self::Void],
            self::Paid => [self::PartiallyPaid, self::Void],
            self::Rejected => [self::Draft, self::Void],
            self::Void => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), strict: true);
    }
}
