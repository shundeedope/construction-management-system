<?php

namespace App\Filament\Resources\OwnerContracts;

use App\Enums\ContractType;
use App\Enums\Permission;
use App\Filament\Resources\OwnerContracts\Pages\CreateOwnerContract;
use App\Filament\Resources\OwnerContracts\Pages\EditOwnerContract;
use App\Filament\Resources\OwnerContracts\Pages\ListOwnerContracts;
use App\Filament\Resources\OwnerContracts\Pages\ViewOwnerContract;
use App\Filament\Resources\OwnerContracts\Schemas\OwnerContractForm;
use App\Filament\Resources\OwnerContracts\Tables\OwnerContractsTable;
use App\Filament\Resources\Projects\RelationManagers\DocumentsRelationManager;
use App\Models\Contract;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OwnerContractResource extends Resource
{
    protected static ?string $model = Contract::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Contracts';

    protected static ?string $navigationLabel = 'Owner Contracts';

    protected static ?string $recordTitleAttribute = 'number';

    /** @return array<int, string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'title'];
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Contract $record */
        return [
            'Project' => $record->project->job_number,
            'Status' => $record->status->getLabel(),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return OwnerContractForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OwnerContractsTable::configure($table);
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
            'index' => ListOwnerContracts::route('/'),
            'create' => CreateOwnerContract::route('/create'),
            'view' => ViewOwnerContract::route('/{record}'),
            'edit' => EditOwnerContract::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return static::getEloquentQuery();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->where('type', ContractType::Owner)
            ->with(['project:id,name,job_number,client_id', 'client:id,company_name']);

        $user = auth()->user();

        if (! $user instanceof User || ! $user->can('viewAny', Contract::class)) {
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
