<?php

namespace App\Filament\Resources\Subcontracts\Tables;

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Filament\Resources\OwnerContracts\Actions\ContractWorkflowActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubcontractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('type')->badge()->sortable(),
                TextColumn::make('project.job_number')->label('Project')->sortable(),
                TextColumn::make('vendor.company_name')->label('Vendor')->searchable(),
                TextColumn::make('original_amount')->label('Original commitment')->money('USD', divideBy: 100)->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('executed_at')->dateTime()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')->options([
                    ContractType::Subcontract->value => ContractType::Subcontract->getLabel(),
                    ContractType::PurchaseOrder->value => ContractType::PurchaseOrder->getLabel(),
                ]),
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
