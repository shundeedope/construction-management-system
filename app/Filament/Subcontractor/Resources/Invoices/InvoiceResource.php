<?php

namespace App\Filament\Subcontractor\Resources\Invoices;

use App\Filament\Resources\Projects\RelationManagers\DocumentsRelationManager;
use App\Filament\Subcontractor\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Subcontractor\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Subcontractor\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Subcontractor\Resources\Invoices\Schemas\InvoiceForm;
use App\Filament\Subcontractor\Resources\Invoices\Tables\InvoicesTable;
use App\Models\Invoice;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return InvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
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
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'edit' => EditInvoice::route('/{record}/edit'),
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

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        /** @var Builder<Invoice> $query */
        $query = parent::getEloquentQuery()
            ->with(['project:id,job_number,name', 'contract:id,number,title,vendor_id', 'approver:id,name']);

        $query->when($user instanceof User, function (Builder $query) use ($user): void {
            /** @var Builder<Invoice> $query */
            $query->ownedByVendor($user);
        }, function (Builder $query): void {
            $query->whereKey([]);
        });

        /** @phpstan-ignore-next-line return.type */
        return $query;
    }
}
