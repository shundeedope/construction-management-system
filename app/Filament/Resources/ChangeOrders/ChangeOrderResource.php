<?php

namespace App\Filament\Resources\ChangeOrders;

use App\Enums\Permission;
use App\Filament\Resources\ChangeOrders\Pages\CreateChangeOrder;
use App\Filament\Resources\ChangeOrders\Pages\EditChangeOrder;
use App\Filament\Resources\ChangeOrders\Pages\ListChangeOrders;
use App\Filament\Resources\ChangeOrders\Schemas\ChangeOrderForm;
use App\Filament\Resources\ChangeOrders\Tables\ChangeOrdersTable;
use App\Filament\Resources\Projects\RelationManagers\DocumentsRelationManager;
use App\Models\ChangeOrder;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ChangeOrderResource extends Resource
{
    protected static ?string $model = ChangeOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'Contracts';

    protected static ?string $navigationLabel = 'Change Orders';

    protected static ?string $recordTitleAttribute = 'number';

    /** @return array<int, string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'title'];
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var ChangeOrder $record */
        return [
            'Project' => $record->project->job_number,
            'Status' => $record->status->getLabel(),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return ChangeOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChangeOrdersTable::configure($table);
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
            'index' => ListChangeOrders::route('/'),
            'create' => CreateChangeOrder::route('/create'),
            'edit' => EditChangeOrder::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return static::getEloquentQuery();
    }

    /**
     * Decided change orders stay reachable read-only so their history and outcome remain visible.
     */
    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('view', $record) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withSum('lineItems', 'amount')
            ->with([
                'project:id,job_number,name',
                'contract:id,number,type',
                'creator:id,name',
                'approver:id,name',
            ]);

        $user = auth()->user();

        if (! $user instanceof User || ! $user->can('viewAny', ChangeOrder::class)) {
            return $query->whereKey([]);
        }

        if ($user->can(Permission::ViewAllFinancials->value)) {
            return $query;
        }

        return $query->whereHas('project.users', fn (Builder $assigned) => $assigned
            ->whereKey($user->getKey())
            ->where('project_user.can_view_financials', true));
    }
}
