<?php

namespace App\Filament\Resources\SubcontractorInvoices\Tables;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\SubcontractorInvoices\Actions\SubcontractorInvoiceWorkflowActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubcontractorInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('due_date')
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('project.job_number')->label('Project')->sortable(),
                TextColumn::make('contract.number')->label('Commitment')->sortable(),
                TextColumn::make('billing_period_end')->label('Period ending')->date()->sortable(),
                TextColumn::make('total')->label('Amount due')->money('USD', divideBy: 100)->sortable()
                    ->summarize(Sum::make()->money('USD', divideBy: 100)),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(InvoiceStatus::class),
                SelectFilter::make('project')->relationship('project', 'name'),
            ])
            ->recordActions([
                SubcontractorInvoiceWorkflowActions::approve(),
                SubcontractorInvoiceWorkflowActions::reject(),
                SubcontractorInvoiceWorkflowActions::submit(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No subcontractor invoices')
            ->emptyStateDescription('Record a vendor bill against a commitment, or wait for subcontractors to submit through their portal.')
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }
}
