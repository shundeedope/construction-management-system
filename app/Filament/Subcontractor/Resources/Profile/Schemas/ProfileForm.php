<?php

namespace App\Filament\Subcontractor\Resources\Profile\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Company details')->schema([
                TextInput::make('company_name')->required()->maxLength(255),
                TextInput::make('primary_contact_name')->maxLength(255),
                TextInput::make('email')->email()->maxLength(255),
                TextInput::make('phone')->tel()->maxLength(255),
            ])->columns(2),
            Section::make('Address')->schema([
                TextInput::make('address_line_1')->maxLength(255)->columnSpanFull(),
                TextInput::make('address_line_2')->maxLength(255)->columnSpanFull(),
                Grid::make(3)->schema([
                    TextInput::make('city')->maxLength(255),
                    TextInput::make('state')->maxLength(255),
                    TextInput::make('postal_code')->maxLength(255),
                ]),
                TextInput::make('country')->required()->default('US')->length(2),
            ]),
        ]);
    }
}
