<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DocumentCategory: string implements HasColor, HasLabel
{
    case Contract = 'contract';
    case ChangeOrder = 'change_order';
    case ClientInvoice = 'client_invoice';
    case SubcontractorInvoice = 'subcontractor_invoice';
    case Plan = 'plan';
    case Compliance = 'compliance';
    case Photo = 'photo';
    case General = 'general';

    public function getLabel(): string
    {
        return match ($this) {
            self::Contract => 'Contract',
            self::ChangeOrder => 'Change order',
            self::ClientInvoice => 'Client invoice',
            self::SubcontractorInvoice => 'Subcontractor invoice',
            self::Plan => 'Plan',
            self::Compliance => 'Compliance',
            self::Photo => 'Photo',
            self::General => 'General',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Contract, self::ChangeOrder => 'info',
            self::ClientInvoice, self::SubcontractorInvoice => 'success',
            self::Plan => 'primary',
            self::Compliance => 'warning',
            self::Photo, self::General => 'gray',
        };
    }

    /**
     * Financial categories are only visible to users allowed to see project financials.
     */
    public function isFinancial(): bool
    {
        return in_array($this, [
            self::Contract,
            self::ChangeOrder,
            self::ClientInvoice,
            self::SubcontractorInvoice,
        ], strict: true);
    }
}
