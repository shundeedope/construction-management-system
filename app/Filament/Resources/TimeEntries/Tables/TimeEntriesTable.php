<?php

namespace App\Filament\Resources\TimeEntries\Tables;

use App\Enums\TimeEntryStatus;
use App\Services\TimeEntryApprovalService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TimeEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.employee_number')
                    ->searchable(),
                TextColumn::make('project.name')
                    ->searchable(),
                TextColumn::make('work_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('regular_hours')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('overtime_hours')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_billable')
                    ->boolean(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('approved_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('approver.name')->label('Approver')->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(TimeEntryStatus::class),
                SelectFilter::make('project')->relationship('project', 'name')->searchable(),
            ])
            ->recordActions([
                Action::make('submit')
                    ->visible(fn ($record): bool => auth()->user()?->can('submit', $record) === true)
                    ->action(fn ($record, TimeEntryApprovalService $service) => $service->submit($record, auth()->user())),
                Action::make('approve')
                    ->color('success')
                    ->visible(fn ($record): bool => auth()->user()?->can('approve', $record) === true)
                    ->action(fn ($record, TimeEntryApprovalService $service) => $service->approve($record, auth()->user())),
                Action::make('reject')
                    ->color('danger')
                    ->schema([Textarea::make('reason')->required()])
                    ->visible(fn ($record): bool => auth()->user()?->can('reject', $record) === true)
                    ->action(fn ($record, array $data, TimeEntryApprovalService $service) => $service->reject($record, auth()->user(), $data['reason'])),
                EditAction::make(),
            ]);
    }
}
