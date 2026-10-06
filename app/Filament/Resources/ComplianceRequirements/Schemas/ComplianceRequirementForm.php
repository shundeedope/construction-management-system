<?php

namespace App\Filament\Resources\ComplianceRequirements\Schemas;

use App\Enums\ComplianceSubject;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ComplianceRequirementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Select::make('applies_to')
                    ->options(ComplianceSubject::class)
                    ->required(),
                Toggle::make('is_required')
                    ->required(),
                TextInput::make('default_reminder_days')
                    ->required()
                    ->numeric()
                    ->default(30),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
