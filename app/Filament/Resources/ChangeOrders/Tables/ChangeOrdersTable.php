<?php

namespace App\Filament\Resources\ChangeOrders\Tables;

use App\Enums\ChangeOrderStatus;
use App\Filament\Resources\ChangeOrders\Actions\ChangeOrderWorkflowActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ChangeOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('type')->badge()->sortable(),
                TextColumn::make('project.job_number')->label('Project')->sortable(),
                TextColumn::make('contract.number')->label('Contract')->sortable(),
                TextColumn::make('line_items_sum_amount')->label('Change value')->money('USD', divideBy: 100),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('submitted_at')->dateTime()->toggleable(),
                TextColumn::make('approved_at')->dateTime()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ChangeOrderStatus::class),
            ])
            ->recordActions([
                ChangeOrderWorkflowActions::approve(),
                ChangeOrderWorkflowActions::reject(),
                ChangeOrderWorkflowActions::submit(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No change orders')
            ->emptyStateDescription('Scope changes to an executed contract are tracked here and revise its value once approved.')
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }
}
