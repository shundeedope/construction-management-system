<?php

namespace App\Filament\Resources\OwnerContracts\Pages;

use App\Filament\Resources\OwnerContracts\Actions\ContractWorkflowActions;
use App\Filament\Resources\OwnerContracts\OwnerContractResource;
use App\Models\Contract;
use App\Services\ActivityLogger;
use App\Services\ContractCalculationService;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ViewOwnerContract extends ViewRecord
{
    protected static string $resource = OwnerContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ContractWorkflowActions::submitForApproval()->record($this->getRecord()),
            ContractWorkflowActions::execute()->record($this->getRecord()),
            ContractWorkflowActions::close()->record($this->getRecord()),
            ContractWorkflowActions::cancel()->record($this->getRecord()),
            EditAction::make(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        /** @var Contract $record */
        $record = $this->getRecord();

        return $schema->components([
            View::make('filament.resources.contracts.pages.view-contract')
                ->viewData([
                    'record' => $record,
                    'approvedChanges' => app(ContractCalculationService::class)->approvedChangeAmount($record),
                    'revisedAmount' => app(ContractCalculationService::class)->revisedAmount($record),
                    'activities' => app(ActivityLogger::class)->queryFor($record)->latest()->limit(5)->get(),
                ]),
        ]);
    }
}
