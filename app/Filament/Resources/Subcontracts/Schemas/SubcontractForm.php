<?php

namespace App\Filament\Resources\Subcontracts\Schemas;

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Models\Project;
use App\Models\Vendor;
use App\Support\Money;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SubcontractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Commitment')
                    ->schema([
                        Select::make('type')->options([
                            ContractType::Subcontract->value => ContractType::Subcontract->getLabel(),
                            ContractType::PurchaseOrder->value => ContractType::PurchaseOrder->getLabel(),
                        ])->default(ContractType::Subcontract)->required()->disabledOn('edit'),
                        TextInput::make('number')->required()->maxLength(255),
                        TextInput::make('title')->required()->maxLength(255),
                        Select::make('project_id')
                            ->options(fn (): array => Project::query()->get()->mapWithKeys(fn (Project $project): array => [$project->id => "{$project->job_number} — {$project->name}"])->all())
                            ->searchable()->required()->disabledOn('edit'),
                        Select::make('vendor_id')->options(fn (): array => Vendor::query()->where('is_active', true)->orderBy('company_name')->pluck('company_name', 'id')->all())->searchable()->required(),
                        Hidden::make('client_id')->default(null),
                        Select::make('status')->options(ContractStatus::class)->default(ContractStatus::Draft)->required()->disabled(),
                        Textarea::make('description')->columnSpanFull(),
                    ]),
                Section::make('Terms')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('retention_basis_points')->label('Retention (basis points)')->integer()->minValue(0)->maxValue(10_000)->default(0)->required(),
                            DatePicker::make('start_date'),
                            DatePicker::make('completion_date')->afterOrEqual('start_date'),
                        ]),
                    ]),
                Section::make('Schedule of Values')
                    ->description('Line amounts are the source of truth for the original commitment.')
                    ->schema([
                        Repeater::make('lineItems')->relationship()->schema([
                            TextInput::make('cost_code')->maxLength(255),
                            Textarea::make('description')->required()->columnSpan(2),
                            TextInput::make('quantity')->numeric()->minValue(0),
                            TextInput::make('unit')->maxLength(255),
                            TextInput::make('unit_price')->label('Unit price')->numeric()->minValue(0)
                                ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : Money::fromCents($state)->toDecimal())
                                ->dehydrateStateUsing(fn ($state): ?int => blank($state) ? null : Money::fromDecimal((string) $state)->cents),
                            TextInput::make('amount')->label('Amount')->required()->numeric()->minValue(0)
                                ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : Money::fromCents($state)->toDecimal())
                                ->dehydrateStateUsing(fn ($state): ?int => blank($state) ? null : Money::fromDecimal((string) $state)->cents),
                            Hidden::make('sort_order')->default(0),
                        ])->columns(3)->orderColumn('sort_order')->addActionLabel('Add Schedule of Values line')->columnSpanFull(),
                    ]),
            ]);
    }
}
