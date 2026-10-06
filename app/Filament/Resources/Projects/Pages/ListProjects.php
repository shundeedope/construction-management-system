<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Enums\ProjectStatus;
use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ProjectStatus::Active))
                ->badge(fn (): int => static::getResource()::getEloquentQuery()->where('status', ProjectStatus::Active)->count()),
            'planning' => Tab::make('Planning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ProjectStatus::Planning)),
            'on_hold' => Tab::make('On hold')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ProjectStatus::OnHold))
                ->badge(fn (): int => static::getResource()::getEloquentQuery()->where('status', ProjectStatus::OnHold)->count())
                ->badgeColor('warning'),
            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ProjectStatus::Completed)),
        ];
    }
}
