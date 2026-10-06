<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContractStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Executed = 'executed';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending approval',
            self::Executed => 'Executed',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingApproval => 'warning',
            self::Executed => 'success',
            self::Closed => 'info',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Original amounts and the Schedule of Values are frozen once a contract leaves draft workflow.
     */
    public function isFinancesLocked(): bool
    {
        return in_array($this, [self::Executed, self::Closed, self::Cancelled], strict: true);
    }

    /**
     * Only executed contracts commit money to the project.
     */
    public function countsTowardsProjectFinancials(): bool
    {
        return in_array($this, [self::Executed, self::Closed], strict: true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingApproval, self::Cancelled],
            self::PendingApproval => [self::Draft, self::Executed, self::Cancelled],
            self::Executed => [self::Closed, self::Cancelled],
            self::Closed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), strict: true);
    }
}
