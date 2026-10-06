<?php

namespace App\Filament\Subcontractor\Resources\ChangeOrders;

use App\Enums\ChangeOrderStatus;
use App\Enums\ChangeOrderType;
use App\Filament\Subcontractor\Resources\ChangeOrders\Pages\ListChangeOrders;
use App\Filament\Subcontractor\Resources\ChangeOrders\Tables\ChangeOrdersTable;
use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ChangeOrderResource extends Resource
{
    protected static ?string $model = ChangeOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentPlus;

    protected static ?string $navigationLabel = 'Approved Changes';

    public static function table(Table $table): Table
    {
        return ChangeOrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChangeOrders::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->vendor_id !== null;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        /** @var Builder<ChangeOrder> $query */
        $query = parent::getEloquentQuery()
            ->where('type', ChangeOrderType::Subcontract)
            ->where('status', ChangeOrderStatus::Approved)
            ->with(['project:id,job_number,name', 'contract:id,number,vendor_id']);

        $query->when($user instanceof User, function (Builder $query) use ($user): void {
            /** @var Builder<ChangeOrder> $query */
            $query->whereHas('contract', function (Builder $contract) use ($user): void {
                /** @var Builder<Contract> $contract */
                $contract->ownedByVendor($user);
            });
        }, function (Builder $query): void {
            $query->whereKey([]);
        });

        /** @phpstan-ignore-next-line return.type */
        return $query;
    }
}
