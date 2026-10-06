<?php

namespace App\Filament\Resources\OwnerContracts\Tables;

use App\Enums\ContractStatus;
use App\Filament\Resources\OwnerContracts\Actions\ContractWorkflowActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OwnerContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('project.job_number')->label('Project')->sortable(),
                TextColumn::make('client.company_name')->label('Client')->searchable(),
                TextColumn::make('original_amount')->label('Original value')->money('USD', divideBy: 100)->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('executed_at')->dateTime()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ContractStatus::class),
            ])
            ->recordActions([
                ContractWorkflowActions::execute(),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
