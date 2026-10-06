<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InvoiceType: string implements HasColor, HasLabel
{
    case Client = 'client';
    case Subcontractor = 'subcontractor';

    public function getLabel(): string
    {
        return match ($this) {
            self::Client => 'Client invoice',
            self::Subcontractor => 'Subcontractor invoice',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Client => 'success',
            self::Subcontractor => 'info',
        };
    }

    /**
     * Client invoices are receivables; subcontractor invoices are payables.
     */
    public function isReceivable(): bool
    {
        return $this === self::Client;
    }

    public function paymentDirection(): PaymentDirection
    {
        return $this->isReceivable() ? PaymentDirection::Incoming : PaymentDirection::Outgoing;
    }
}
