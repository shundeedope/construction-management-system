<?php

namespace App\Filament\Subcontractor\Resources\Invoices\Schemas;

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\InvoiceStatus;
use App\Models\Contract;
use App\Models\ContractLineItem;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Support\Money;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Invoice details')
                ->schema([
                    TextInput::make('number')->required()->maxLength(255),
                    Select::make('project_id')
                        ->options(fn (): array => self::projects())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('contract_id', null))
                        ->required()
                        ->disabledOn('edit'),
                    Select::make('contract_id')
                        ->options(fn (Get $get): array => self::contracts($get('project_id')))
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Set $set, mixed $state): void {
                            $contract = self::vendorContract($state);

                            if (! $contract instanceof Contract) {
                                $set('lineItems', []);

                                return;
                            }

                            $set('lineItems', $contract->lineItems()->orderBy('sort_order')->get()
                                ->map(fn (ContractLineItem $line): array => [
                                    'contract_line_item_id' => $line->getKey(),
                                    'scheduled_value' => Money::fromCents($line->amount)->toDecimal(),
                                    'previously_billed' => '0.00',
                                    'work_completed_this_period' => '0.00',
                                    'materials_stored' => '0.00',
                                    'retention_amount' => '0.00',
                                    'current_due' => '0.00',
                                    'sort_order' => $line->sort_order,
                                ])->all());
                        })
                        ->required()
                        ->disabled(fn (Get $get): bool => blank($get('project_id')))
                        ->disabledOn('edit'),
                    TextInput::make('status')->default(InvoiceStatus::Draft->getLabel())->disabled()->dehydrated(false),
                    Grid::make(2)->schema([
                        DatePicker::make('billing_period_start')->required(),
                        DatePicker::make('billing_period_end')->afterOrEqual('billing_period_start')->required(),
                        DatePicker::make('issue_date')->required(),
                        DatePicker::make('due_date')->afterOrEqual('issue_date')->required(),
                    ]),
                    Textarea::make('notes')->columnSpanFull(),
                ]),
            Section::make('Schedule of Values')
                ->description('Values are calculated from your executed contract and approved changes.')
                ->schema([
                    Repeater::make('lineItems')->relationship()->schema([
                        Hidden::make('contract_line_item_id')->required(),
                        TextInput::make('scheduled_value')->label('Scheduled value')->disabled()->formatStateUsing(fn (?int $state): ?string => $state === null ? null : Money::fromCents($state)->toDecimal()),
                        TextInput::make('previously_billed')->label('Previously billed')->disabled()->formatStateUsing(fn (?int $state): ?string => $state === null ? null : Money::fromCents($state)->toDecimal()),
                        TextInput::make('work_completed_this_period')->label('Work this period')->required()->numeric()->minValue(0)->default('0.00')
                            ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : Money::fromCents($state)->toDecimal())
                            ->dehydrateStateUsing(fn (mixed $state): int => blank($state) ? 0 : Money::fromDecimal((string) $state)->cents),
                        TextInput::make('materials_stored')->label('Stored materials')->required()->numeric()->minValue(0)->default('0.00')
                            ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : Money::fromCents($state)->toDecimal())
                            ->dehydrateStateUsing(fn (mixed $state): int => blank($state) ? 0 : Money::fromDecimal((string) $state)->cents),
                        TextInput::make('retention_amount')->label('Retention')->disabled()->formatStateUsing(fn (?int $state): ?string => $state === null ? null : Money::fromCents($state)->toDecimal()),
                        TextInput::make('current_due')->label('Current due')->disabled()->formatStateUsing(fn (?int $state): ?string => $state === null ? null : Money::fromCents($state)->toDecimal()),
                        Hidden::make('sort_order')->default(0),
                    ])->columns(3)->addable(false)->deletable(false)->reorderable(false)->columnSpanFull()
                        ->disabled(fn (?Invoice $record): bool => $record?->status?->isFinancesLocked() ?? false),
                ]),
        ]);
    }

    /** @return array<int, string> */
    private static function projects(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        return Project::query()->visibleToVendor($user)->orderBy('job_number')->get(['id', 'job_number', 'name'])
            ->mapWithKeys(fn (Project $project): array => [$project->id => "{$project->job_number} — {$project->name}"])->all();
    }

    /** @return array<int, string> */
    private static function contracts(mixed $projectId): array
    {
        $user = auth()->user();

        if (! $user instanceof User || blank($projectId)) {
            return [];
        }

        return Contract::query()->ownedByVendor($user)->where('project_id', $projectId)
            ->whereIn('type', [ContractType::Subcontract, ContractType::PurchaseOrder])
            ->where('status', ContractStatus::Executed)->orderBy('number')->pluck('number', 'id')->all();
    }

    private static function vendorContract(mixed $contractId): ?Contract
    {
        $user = auth()->user();

        if (! $user instanceof User || blank($contractId)) {
            return null;
        }

        /** @var Contract|null $result */
        $result = Contract::query()->ownedByVendor($user)->find($contractId);

        return $result;
    }
}
