<?php

namespace App\Filament\Resources\OwnerContracts\Pages;

use App\Filament\Resources\OwnerContracts\Actions\ContractWorkflowActions;
use App\Filament\Resources\OwnerContracts\OwnerContractResource;
use App\Models\Contract;
use App\Services\ContractCalculationService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOwnerContract extends EditRecord
{
    protected static string $resource = OwnerContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ContractWorkflowActions::submitForApproval()->record($this->getRecord()),
            ContractWorkflowActions::execute()->record($this->getRecord()),
            ContractWorkflowActions::close()->record($this->getRecord()),
            ContractWorkflowActions::cancel()->record($this->getRecord()),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        /** @var Contract $record */
        $record = $this->record;
        app(ContractCalculationService::class)->syncOriginalAmount($record);
    }
}
