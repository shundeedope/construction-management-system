<?php

namespace App\Filament\Resources\LeaveTransactions\Schemas;

use App\Enums\LeaveTransactionType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class LeaveTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('employee_id')
                    ->relationship('employee', 'employee_number')
                    ->searchable()
                    ->required(),
                Select::make('type')
                    ->options(LeaveTransactionType::class)
                    ->required(),
                TextInput::make('hours')
                    ->required()
                    ->numeric()
                    ->minValue(fn (Get $get): ?int => $get('type') === LeaveTransactionType::Adjustment->value ? null : 0),
                DatePicker::make('effective_date')
                    ->required(),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
