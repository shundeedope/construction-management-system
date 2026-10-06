<?php

namespace App\Filament\Subcontractor\Resources\Profile;

use App\Filament\Subcontractor\Resources\Profile\Pages\EditProfile;
use App\Filament\Subcontractor\Resources\Profile\Schemas\ProfileForm;
use App\Models\User;
use App\Models\Vendor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProfileResource extends Resource
{
    protected static ?string $model = Vendor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $navigationLabel = 'Company profile';

    public static function form(Schema $schema): Schema
    {
        return ProfileForm::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'edit' => EditProfile::route('/'),
        ];
    }

    public static function getNavigationUrl(): string
    {
        return static::getIndexUrl();
    }

    public static function getIndexUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false): string
    {
        return static::getUrl('edit', $parameters, $isAbsolute, $panel, $tenant, $shouldGuessMissingParameters);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->vendor_id !== null;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->vendor_id === $record->getKey();
    }

    public static function canView(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->when($user instanceof User, fn (Builder $query) => $query->whereKey($user->vendor_id), fn (Builder $query) => $query->whereKey([]));
    }
}
