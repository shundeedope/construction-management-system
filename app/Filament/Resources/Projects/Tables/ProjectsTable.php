<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Enums\Permission;
use App\Enums\ProjectStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('job_number')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('client.company_name')->label('Client')->searchable()->sortable(),
                TextColumn::make('projectManager.name')->label('Manager')->toggleable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('revised_owner_value')->label('Revised value')->money('USD', divideBy: 100)->sortable()->visible(fn (): bool => auth()->user()?->can(Permission::ViewFinancials->value) === true),
                TextColumn::make('revised_commitments')->label('Commitments')->money('USD', divideBy: 100)->sortable()->visible(fn (): bool => auth()->user()?->can(Permission::ViewFinancials->value) === true),
                TextColumn::make('gross_margin')->label('Gross margin')->money('USD', divideBy: 100)->sortable()->visible(fn (): bool => auth()->user()?->can(Permission::ViewFinancials->value) === true),
                TextColumn::make('expected_completion_date')->date()->label('Expected completion')->sortable()
                    ->color(fn ($state): ?string => $state?->isPast() ? 'warning' : null),
                IconColumn::make('actual_completion_date')->label('Completed')->boolean(fn ($record): bool => $record->actual_completion_date !== null)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ProjectStatus::class),
                SelectFilter::make('client')->relationship('client', 'company_name')->searchable(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No projects yet')
            ->emptyStateDescription('Create your first project to start tracking contracts, billing, and labor.')
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }
}
