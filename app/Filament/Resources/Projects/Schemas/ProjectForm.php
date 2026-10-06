<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\ProjectStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Project details')->schema(self::detailsFields()),
                Section::make('Schedule')->schema(self::scheduleFields()),
                Section::make('Site address')->schema(self::siteAddressFields()),
                Section::make('Internal notes')->schema(self::notesFields()),
            ]);
    }

    /** @return array<int, Component> */
    public static function detailsFields(): array
    {
        return [
            TextInput::make('job_number')
                ->label('Job number')
                ->placeholder('Assigned automatically when the project is created')
                ->disabled()
                ->dehydrated(false),
            TextInput::make('name')->required()->maxLength(255),
            Select::make('client_id')
                ->relationship('client', 'company_name')
                ->searchable()
                ->required(),
            Select::make('project_manager_id')
                ->relationship('projectManager', 'name')
                ->searchable(),
            Select::make('status')->options(ProjectStatus::class)->required()->default(ProjectStatus::Planning),
            Textarea::make('description')->columnSpanFull(),
        ];
    }

    /** @return array<int, Component> */
    public static function scheduleFields(): array
    {
        return [
            Grid::make(3)->schema([
                DatePicker::make('start_date'),
                DatePicker::make('expected_completion_date')->afterOrEqual('start_date'),
                DatePicker::make('actual_completion_date')->afterOrEqual('start_date'),
            ]),
        ];
    }

    /** @return array<int, Component> */
    public static function siteAddressFields(): array
    {
        return [
            TextInput::make('address_line_1')->maxLength(255)->columnSpanFull(),
            TextInput::make('address_line_2')->maxLength(255)->columnSpanFull(),
            Grid::make(3)->schema([
                TextInput::make('city')->maxLength(255),
                TextInput::make('state')->maxLength(255),
                TextInput::make('postal_code')->maxLength(255),
            ]),
            TextInput::make('country')->required()->default('US')->length(2),
        ];
    }

    /** @return array<int, Component> */
    public static function notesFields(): array
    {
        return [
            Textarea::make('internal_notes')->columnSpanFull(),
        ];
    }
}
