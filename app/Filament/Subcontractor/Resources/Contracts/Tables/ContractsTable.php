<?php

namespace App\Filament\Subcontractor\Resources\Contracts\Tables;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Services\ContractCalculationService;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('project.job_number')->label('Project')->sortable(),
                TextColumn::make('original_amount')->label('Commitment')->money('USD', divideBy: 100)->sortable(),
                TextColumn::make('approved_changes')->label('Approved changes')
                    ->state(fn (Contract $record): int => app(ContractCalculationService::class)->approvedChangeAmount($record)->cents)
                    ->money('USD', divideBy: 100),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('executed_at')->date()->label('Executed'),
            ])
            ->filters([
                SelectFilter::make('status')->options(ContractStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
