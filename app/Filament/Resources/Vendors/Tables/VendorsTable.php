<?php

namespace App\Filament\Resources\Vendors\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VendorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')->searchable()->sortable(),
                TextColumn::make('vendor_type')->badge(),
                TextColumn::make('primary_contact_name')->searchable()->toggleable(),
                TextColumn::make('insurance_expires_at')->date()->sortable()
                    ->color(fn ($state): ?string => $state?->isPast() ? 'danger' : ($state?->isBefore(now()->addDays(30)) ? 'warning' : null)),
                IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
                Filter::make('insurance_expiring')
                    ->label('Insurance expired or due within 30 days')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('insurance_expires_at')->where('insurance_expires_at', '<=', now()->addDays(30))),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
