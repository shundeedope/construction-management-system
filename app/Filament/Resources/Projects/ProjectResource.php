<?php

namespace App\Filament\Resources\Projects;

use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\Schemas\ProjectForm;
use App\Filament\Resources\Projects\Tables\ProjectsTable;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectFinancialReportQuery;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|\UnitEnum|null $navigationGroup = 'Projects';

    protected static ?string $recordTitleAttribute = 'name';

    /** @return array<int, string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['job_number', 'name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Project $record */
        return "{$record->job_number} — {$record->name}";
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Project $record */
        return [
            'Client' => $record->client->company_name,
            'Status' => $record->status->getLabel(),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return ProjectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DocumentsRelationManager::class,
            RelationManagers\UsersRelationManager::class,
            RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'view' => ViewProject::route('/{record}'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return static::getEloquentQuery();
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Project> $query */
        $query = parent::getEloquentQuery()->with([
            'client:id,company_name',
            'projectManager:id,name',
        ]);

        $user = auth()->user();

        if (! $user instanceof User) {
            /** @phpstan-ignore-next-line return.type */
            return $query->whereKey([]);
        }

        $query->visibleTo($user);

        if ($user->can('view_financials')) {
            app(ProjectFinancialReportQuery::class)->apply($query);
        }

        /** @phpstan-ignore-next-line return.type */
        return $query;
    }
}
