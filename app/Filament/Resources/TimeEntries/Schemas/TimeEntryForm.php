<?php

namespace App\Filament\Resources\TimeEntries\Schemas;

use App\Models\Project;
use App\Services\CompanySettings;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TimeEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('employee_id')
                    ->relationship('employee', 'employee_number')
                    ->searchable()
                    ->default(fn (): ?int => auth()->user()?->employee?->getKey())
                    ->disabled(fn (): bool => ! auth()->user()?->can('view_all_time_entries'))
                    ->dehydrated()
                    ->required(),
                Select::make('project_id')
                    ->options(fn (): array => Project::query()->visibleTo(auth()->user())->pluck('name', 'id')->all())
                    ->searchable()
                    ->required(),
                DatePicker::make('work_date')
                    ->required(),
                TextInput::make('regular_hours')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(fn (): float => app(CompanySettings::class)->maximumDailyHours())
                    ->default(0),
                TextInput::make('overtime_hours')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(fn (): float => app(CompanySettings::class)->maximumDailyHours())
                    ->default(0),
                Toggle::make('is_billable')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }
}
