<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\ProjectRole;
use App\Models\Project;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Project && auth()->user()?->can('manageTeam', $ownerRecord) === true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('project_role')->options(ProjectRole::class)->required(),
                Toggle::make('can_view_financials'),
                Toggle::make('can_manage_documents'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('project_role')->badge(),
                TextColumn::make('can_view_financials')->badge()->state(fn ($record): string => $record->pivot->can_view_financials ? 'Financials' : 'No financials'),
                TextColumn::make('can_manage_documents')->badge()->state(fn ($record): string => $record->pivot->can_manage_documents ? 'Documents' : 'No documents'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Select::make('project_role')->options(ProjectRole::class)->required(),
                        Toggle::make('can_view_financials'),
                        Toggle::make('can_manage_documents'),
                    ])
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query->orderBy('name'))
                    ->authorize(fn (): bool => auth()->user()?->can('manageTeam', $this->getOwnerRecord()) === true),
            ])
            ->recordActions([
                EditAction::make()->authorize(fn (): bool => auth()->user()?->can('manageTeam', $this->getOwnerRecord()) === true),
                DetachAction::make()->authorize(fn (): bool => auth()->user()?->can('manageTeam', $this->getOwnerRecord()) === true),
            ]);
    }
}
