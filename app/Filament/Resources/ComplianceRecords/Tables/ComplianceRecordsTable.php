<?php

namespace App\Filament\Resources\ComplianceRecords\Tables;

use App\Enums\ComplianceStatus;
use App\Services\ComplianceStatusService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ComplianceRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('requirement.name')->label('Requirement')->searchable(),
                TextColumn::make('employee.employee_number')->searchable(),
                TextColumn::make('vendor.company_name')->searchable(),
                TextColumn::make('project.name')->searchable(),
                TextColumn::make('document.name')->label('Evidence')->searchable(),
                TextColumn::make('expires_at')->date()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('verifier.name')->label('Verified by')->toggleable(),
                TextColumn::make('verified_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ComplianceStatus::class),
                SelectFilter::make('requirement')->relationship('requirement', 'name')->searchable(),
                SelectFilter::make('project')->relationship('project', 'name')->searchable(),
            ])
            ->recordActions([
                Action::make('verify')
                    ->color('success')
                    ->visible(fn ($record): bool => auth()->user()?->can('verify', $record) === true)
                    ->action(fn ($record, ComplianceStatusService $service) => $service->verify($record, auth()->user())),
                EditAction::make(),
            ]);
    }
}
