<?php

namespace App\Filament\Subcontractor\Resources\Invoices\Pages;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Filament\Subcontractor\Resources\Invoices\InvoiceResource;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\User;
use App\Services\SubcontractorInvoiceCalculationService;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        /** @var Contract $contract */
        $contract = Contract::query()->ownedByVendor($user)->findOrFail($data['contract_id']);
        abort_unless($contract->project_id === (int) $data['project_id'], 403);

        return [
            ...$data,
            'project_id' => $contract->project_id,
            'type' => InvoiceType::Subcontractor,
            'status' => InvoiceStatus::Draft,
            'created_by' => $user->getKey(),
        ];
    }

    protected function afterCreate(): void
    {
        /** @var Invoice $record */
        $record = $this->record;
        app(SubcontractorInvoiceCalculationService::class)->sync($record);
    }
}
