<?php

namespace App\Filament\Subcontractor\Resources\Projects;

use App\Filament\Subcontractor\Resources\Projects\Pages\ListProjects;
use App\Filament\Subcontractor\Resources\Projects\Tables\ProjectsTable;
use App\Models\Project;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function table(Table $table): Table
    {
        return ProjectsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return static::getEloquentQuery();
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->vendor_id !== null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        /** @var Builder<Project> $query */
        $query = parent::getEloquentQuery()
            ->with('projectManager:id,name');

        $query->when($user instanceof User, function (Builder $query) use ($user): void {
            /** @var Builder<Project> $query */
            $query->visibleToVendor($user);
        }, function (Builder $query): void {
            $query->whereKey([]);
        });

        /** @phpstan-ignore-next-line return.type */
        return $query;
    }
}
