<?php

namespace App\Filament\Resources\LeaveTransactions;

use App\Enums\Permission;
use App\Filament\Resources\LeaveTransactions\Pages\CreateLeaveTransaction;
use App\Filament\Resources\LeaveTransactions\Pages\EditLeaveTransaction;
use App\Filament\Resources\LeaveTransactions\Pages\ListLeaveTransactions;
use App\Filament\Resources\LeaveTransactions\Schemas\LeaveTransactionForm;
use App\Filament\Resources\LeaveTransactions\Tables\LeaveTransactionsTable;
use App\Models\LeaveTransaction;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeaveTransactionResource extends Resource
{
    protected static ?string $model = LeaveTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Labor';

    public static function canAccess(): bool
    {
        return auth()->user()?->canAny([Permission::ViewOwnLeave->value, Permission::ManageLeave->value]) === true;
    }

    public static function form(Schema $schema): Schema
    {
        return LeaveTransactionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeaveTransactionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeaveTransactions::route('/'),
            'create' => CreateLeaveTransaction::route('/create'),
            'edit' => EditLeaveTransaction::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['employee.user:id,name', 'creator:id,name']);
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query->whereKey([]);
        }

        if ($user->can(Permission::ManageLeave->value)) {
            return $query;
        }

        return $query->whereHas('employee', fn (Builder $employees) => $employees->where('user_id', $user->getKey()));
    }
}
