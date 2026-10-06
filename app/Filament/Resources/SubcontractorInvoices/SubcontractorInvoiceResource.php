<?php

namespace App\Filament\Resources\SubcontractorInvoices;

use App\Enums\InvoiceType;
use App\Enums\Permission;
use App\Filament\Resources\ClientInvoices\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\SubcontractorInvoices\Pages\CreateSubcontractorInvoice;
use App\Filament\Resources\SubcontractorInvoices\Pages\EditSubcontractorInvoice;
use App\Filament\Resources\SubcontractorInvoices\Pages\ListSubcontractorInvoices;
use App\Filament\Resources\SubcontractorInvoices\Schemas\SubcontractorInvoiceForm;
use App\Filament\Resources\SubcontractorInvoices\Tables\SubcontractorInvoicesTable;
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

class SubcontractorInvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $recordTitleAttribute = 'number';

    protected static string|\UnitEnum|null $navigationGroup = 'Financials';

    protected static ?string $navigationLabel = 'Subcontractor Invoices';

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Invoice $record */
        return [
            'Project' => $record->project->job_number,
            'Vendor' => $record->contract->vendor->company_name,
            'Status' => $record->status->getLabel(),
            'Amount' => Money::fromCents($record->total)->format(),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return SubcontractorInvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubcontractorInvoicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubcontractorInvoices::route('/'),
            'create' => CreateSubcontractorInvoice::route('/create'),
            'edit' => EditSubcontractorInvoice::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return static::getEloquentQuery();
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('view', $record) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->where('type', InvoiceType::Subcontractor)
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
