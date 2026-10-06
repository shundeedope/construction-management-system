<?php

namespace App\Filament\Resources\OwnerContracts\Pages;

use App\Filament\Resources\OwnerContracts\OwnerContractResource;
use App\Models\Contract;
use App\Models\Project;
use App\Services\ContractCalculationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Gate;

class CreateOwnerContract extends CreateRecord
{
    protected static string $resource = OwnerContractResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var Project $project */
        $project = Project::query()->findOrFail($data['project_id']);
        Gate::authorize('viewFinancials', $project);

        $data['client_id'] = $project->client_id;
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Contract $record */
        $record = $this->record;
        app(ContractCalculationService::class)->syncOriginalAmount($record);
    }
}
