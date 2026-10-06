<?php

namespace App\Filament\Resources\ClientInvoices;

use App\Enums\InvoiceType;
use App\Enums\Permission;
use App\Filament\Resources\ClientInvoices\Pages\CreateClientInvoice;
use App\Filament\Resources\ClientInvoices\Pages\EditClientInvoice;
use App\Filament\Resources\ClientInvoices\Pages\ListClientInvoices;
use App\Filament\Resources\ClientInvoices\Schemas\ClientInvoiceForm;
use App\Filament\Resources\ClientInvoices\Tables\ClientInvoicesTable;
use App\Filament\Resources\Projects\RelationManagers\DocumentsRelationManager;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Money;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ClientInvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $recordTitleAttribute = 'number';

    protected static string|\UnitEnum|null $navigationGroup = 'Financials';

    protected static ?string $navigationLabel = 'Client Invoices';

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Invoice $record */
        return [
            'Project' => $record->project->job_number,
            'Status' => $record->status->getLabel(),
            'Amount' => Money::fromCents($record->total)->format(),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return ClientInvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientInvoicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
            RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClientInvoices::route('/'),
            'create' => CreateClientInvoice::route('/create'),
            'edit' => EditClientInvoice::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return static::getEloquentQuery();
    }

    /**
     * Locked invoices stay reachable read-only so lifecycle actions (send, void, PDF) remain available.
     */
    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('view', $record) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->where('type', InvoiceType::Client)
            ->with(['project:id,job_number,name', 'contract:id,number,title', 'creator:id,name', 'approver:id,name']);

        $user = auth()->user();

        if (! $user instanceof User || ! $user->can('viewAny', Invoice::class)) {
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
