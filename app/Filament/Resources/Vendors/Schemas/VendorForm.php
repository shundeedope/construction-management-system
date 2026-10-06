<?php

namespace App\Filament\Resources\Vendors\Schemas;

use App\Enums\VendorType;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VendorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Organization')
                    ->schema([
                        TextInput::make('company_name')->required()->maxLength(255),
                        Grid::make(2)->schema([
                            Select::make('vendor_type')->options(VendorType::class),
                            DatePicker::make('insurance_expires_at')->label('Insurance expires'),
                            TextInput::make('primary_contact_name')->maxLength(255),
                            TextInput::make('email')->email()->maxLength(255),
                            TextInput::make('phone')->tel()->maxLength(255),
                            TextInput::make('tax_identifier')->maxLength(255),
                        ]),
                        Checkbox::make('is_active')->default(true),
                        Textarea::make('notes')->columnSpanFull(),
                    ]),
                Section::make('Billing address')
                    ->schema([
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
