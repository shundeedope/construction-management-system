<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentDirection: string implements HasColor, HasLabel
{
    case Incoming = 'incoming';
    case Outgoing = 'outgoing';

    public function getLabel(): string
    {
        return match ($this) {
            self::Incoming => 'Incoming',
            self::Outgoing => 'Outgoing',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Incoming => 'success',
            self::Outgoing => 'warning',
        };
    }

    public function invoiceType(): InvoiceType
    {
        return $this === self::Incoming ? InvoiceType::Client : InvoiceType::Subcontractor;
    }
}
