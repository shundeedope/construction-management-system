<?php

namespace App\Filament\Resources\ChangeOrders\Schemas;

use App\Enums\ChangeOrderStatus;
use App\Enums\ChangeOrderType;
use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\Project;
use App\Services\ActivityLogger;
use App\Support\Money;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ChangeOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Change order')
                    ->schema([
                        Select::make('type')
                            ->options(ChangeOrderType::class)
                            ->default(ChangeOrderType::Owner)
                            ->required()
                            ->disabledOn('edit'),
                        TextInput::make('number')->required()->maxLength(255),
                        TextInput::make('title')->required()->maxLength(255),
                        Select::make('project_id')
                            ->options(fn (): array => Project::query()
                                ->orderBy('job_number')
                                ->get(['id', 'job_number', 'name'])
                                ->mapWithKeys(fn (Project $project): array => [$project->id => "{$project->job_number} — {$project->name}"])
                                ->all())
                            ->searchable()
                            ->live()
                            ->default(fn (): ?int => request()->integer('project_id') ?: null)
                            ->required()
                            ->disabledOn('edit'),
                        Select::make('contract_id')
                            ->options(function (Get $get): array {
                                $type = $get('type');
                                $isOwnerChange = $type === ChangeOrderType::Owner || $type === ChangeOrderType::Owner->value;

                                return Contract::query()
                                    ->where('project_id', $get('project_id'))
                                    ->where('status', 'executed')
                                    ->when(
                                        $isOwnerChange,
                                        fn ($query) => $query->where('type', 'owner'),
                                        fn ($query) => $query->whereIn('type', ['subcontract', 'purchase_order']),
                                    )
                                    ->orderBy('number')
                                    ->pluck('number', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->required()
                            ->disabled(fn (Get $get): bool => blank($get('project_id')))
                            ->disabledOn('edit'),
                        Select::make('status')->options(ChangeOrderStatus::class)->default(ChangeOrderStatus::Draft)->required()->disabled(),
                        DatePicker::make('requested_at'),
                        TextInput::make('schedule_impact_days')->integer(),
                        Textarea::make('description')->columnSpanFull(),
                        Hidden::make('created_by'),
                    ]),
                Section::make('Change details')
                    ->schema([
                        Repeater::make('lineItems')
                            ->relationship()
                            ->schema([
                                TextInput::make('cost_code')->maxLength(255),
                                Textarea::make('description')->required()->columnSpan(2),
                                TextInput::make('amount')
                                    ->label('Amount')
                                    ->required()
                                    ->numeric()
                                    ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : Money::fromCents($state)->toDecimal())
                                    ->dehydrateStateUsing(fn ($state): ?int => blank($state) ? null : Money::fromDecimal((string) $state)->cents),
                                Hidden::make('sort_order')->default(0),
                            ])
                            ->columns(3)
                            ->orderColumn('sort_order')
                            ->addActionLabel('Add change-order line')
                            ->columnSpanFull()
                            ->disabled(fn (?ChangeOrder $record): bool => $record?->status !== null && $record->status !== ChangeOrderStatus::Draft),
                    ]),
                Section::make('Activity history')
                    ->visible(fn (?ChangeOrder $record): bool => $record?->getKey() !== null)
                    ->schema([
                        View::make('filament.resources.change-orders.components.activity-history')
                            ->viewData(fn (ChangeOrder $record): array => [
                                'activities' => app(ActivityLogger::class)->queryFor($record)->latest()->limit(5)->get(),
                            ]),
                    ]),
            ]);
    }
}
