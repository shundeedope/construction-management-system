<?php

namespace App\Filament\Resources\ClientInvoices\Tables;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\ClientInvoices\Actions\ClientInvoiceWorkflowActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ClientInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('due_date')
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('project.job_number')->label('Project')->sortable(),
                TextColumn::make('contract.number')->label('Owner contract')->sortable(),
                TextColumn::make('billing_period_end')->label('Period ending')->date()->sortable(),
                TextColumn::make('total')->label('Amount due')->money('USD', divideBy: 100)->sortable()
                    ->summarize(Sum::make()->money('USD', divideBy: 100)),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('sent_at')->dateTime()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(InvoiceStatus::class),
                SelectFilter::make('project')->relationship('project', 'name'),
            ])
            ->recordActions([
                ClientInvoiceWorkflowActions::approve(),
                ClientInvoiceWorkflowActions::send(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No client invoices')
            ->emptyStateDescription('Bill your client from an executed owner contract — line items prefill from the Schedule of Values.')
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }
}
