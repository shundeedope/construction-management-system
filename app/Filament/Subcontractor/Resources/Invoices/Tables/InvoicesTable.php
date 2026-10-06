<?php

namespace App\Filament\Subcontractor\Resources\Invoices\Tables;

use App\Enums\InvoiceStatus;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('project.job_number')->label('Project')->sortable(),
                TextColumn::make('contract.number')->label('Contract')->sortable(),
                TextColumn::make('billing_period_end')->label('Period ending')->date()->sortable(),
                TextColumn::make('total')->label('Amount due')->money('USD', divideBy: 100)->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('rejection_reason')->label('Rejection reason')->limit(50)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(InvoiceStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
