<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Enums\Permission;
use App\Enums\ProjectRole;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Projects\Schemas\ProjectForm;
use App\Models\Project;
use App\Models\User;
use App\Services\NumberSequenceService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;

class CreateProject extends CreateRecord
{
    use HasWizard;

    protected static string $resource = ProjectResource::class;

    /** @return array<int, Step> */
    public function getSteps(): array
    {
        return [
            Step::make('Details')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->description('Client, manager, and status')
                ->schema(ProjectForm::detailsFields())
                ->columns(2),
            Step::make('Schedule & site')
                ->icon(Heroicon::OutlinedMapPin)
                ->description('Dates and site address')
                ->schema([
                    ...ProjectForm::scheduleFields(),
                    ...ProjectForm::siteAddressFields(),
                ]),
            Step::make('Notes')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->description('Internal notes for the team')
                ->schema(ProjectForm::notesFields()),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['job_number'] = app(NumberSequenceService::class)->generate('project');

        return $data;
    }

    protected function afterCreate(): void
    {
        $creator = auth()->user();

        if (! $creator instanceof User) {
            return;
        }

        /** @var Project $project */
        $project = $this->record;
        $assignments = [
            $creator->getKey() => [
                'project_role' => ProjectRole::Other->value,
                'can_view_financials' => $creator->can(Permission::ViewFinancials->value),
                'can_manage_documents' => $creator->can(Permission::ManageDocuments->value),
            ],
        ];

        if ($project->project_manager_id !== null) {
            $assignments[$project->project_manager_id] = [
                'project_role' => ProjectRole::ProjectManager->value,
                'can_view_financials' => true,
                'can_manage_documents' => true,
            ];
        }

        $project->users()->syncWithoutDetaching($assignments);
    }
}
