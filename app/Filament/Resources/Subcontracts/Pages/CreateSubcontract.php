<?php

namespace App\Filament\Resources\Subcontracts\Pages;

use App\Filament\Resources\Subcontracts\SubcontractResource;
use App\Models\Contract;
use App\Models\Project;
use App\Services\ContractCalculationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Gate;

class CreateSubcontract extends CreateRecord
{
    protected static string $resource = SubcontractResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var Project $project */
        $project = Project::query()->findOrFail($data['project_id']);
        Gate::authorize('viewFinancials', $project);
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
