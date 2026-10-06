<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Enums\ChangeOrderStatus;
use App\Filament\Resources\ChangeOrders\ChangeOrderResource;
use App\Filament\Resources\ClientInvoices\ClientInvoiceResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\ChangeOrder;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Services\ActivityLogger;
use App\Services\ProjectFinancialSummaryService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newChangeOrder')
                ->label('New change order')
                ->icon(Heroicon::OutlinedPlusCircle)
                ->color('gray')
                ->url(fn (): string => ChangeOrderResource::getUrl('create').'?project_id='.$this->getRecord()->getKey())
                ->visible(fn (): bool => auth()->user()?->can('create', ChangeOrder::class) === true),
            Action::make('newClientInvoice')
                ->label('New client invoice')
                ->icon(Heroicon::OutlinedDocumentPlus)
                ->color('gray')
                ->url(fn (): string => ClientInvoiceResource::getUrl('create').'?project_id='.$this->getRecord()->getKey())
                ->visible(fn (): bool => auth()->user()?->can('create', Invoice::class) === true),
            EditAction::make()->visible(fn (Project $record): bool => auth()->user()?->can('update', $record) === true),
        ];
    }

    public function content(Schema $schema): Schema
    {
        /** @var Project $project */
        $project = $this->getRecord();

        return $schema->components([
            View::make('filament.resources.projects.pages.view-project')
                ->viewData([
                    'record' => $project,
                    'financialSummary' => app(ProjectFinancialSummaryService::class)->summary($project),
                    'pendingChangeOrders' => $project->changeOrders()
                        ->where('status', ChangeOrderStatus::Submitted)
                        ->count(),
                    'laborSummary' => TimeEntry::query()
                        ->whereBelongsTo($project)
                        ->selectRaw('COALESCE(SUM(regular_hours), 0) as regular_hours')
                        ->selectRaw('COALESCE(SUM(overtime_hours), 0) as overtime_hours')
                        ->selectRaw("COALESCE(SUM(CASE WHEN status = 'approved' THEN regular_hours + overtime_hours ELSE 0 END), 0) as approved_hours")
                        ->first(),
                    'team' => $project->users()->orderBy('name')->get(),
                    'activities' => app(ActivityLogger::class)
                        ->queryForProject($project)
                        ->with('causer')
                        ->latest()
                        ->limit(8)
                        ->get(),
                    'contracts' => $project->contracts()
                        ->orderBy('number')
                        ->get(['id', 'project_id', 'number', 'title', 'type', 'status', 'original_amount']),
                ]),
            $this->getRelationManagersContentComponent(),
        ]);
    }
}
