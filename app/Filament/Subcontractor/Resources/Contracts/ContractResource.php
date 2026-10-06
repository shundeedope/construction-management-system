<?php

namespace App\Filament\Subcontractor\Resources\Contracts;

use App\Enums\ContractType;
use App\Filament\Resources\Projects\RelationManagers\DocumentsRelationManager;
use App\Filament\Subcontractor\Resources\Contracts\Pages\ListContracts;
use App\Filament\Subcontractor\Resources\Contracts\Pages\ViewContract;
use App\Filament\Subcontractor\Resources\Contracts\Tables\ContractsTable;
use App\Models\Contract;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContractResource extends Resource
{
    protected static ?string $model = Contract::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'number';

    public static function table(Table $table): Table
    {
        return ContractsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContracts::route('/'),
            'view' => ViewContract::route('/{record}'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return static::getEloquentQuery();
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->vendor_id !== null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        /** @var Builder<Contract> $query */
        $query = parent::getEloquentQuery()
            ->whereIn('type', [ContractType::Subcontract, ContractType::PurchaseOrder])
            ->with('project:id,job_number,name');

        $query->when($user instanceof User, function (Builder $query) use ($user): void {
            /** @var Builder<Contract> $query */
            $query->ownedByVendor($user);
        }, function (Builder $query): void {
            $query->whereKey([]);
        });

        /** @phpstan-ignore-next-line return.type */
        return $query;
    }
}
