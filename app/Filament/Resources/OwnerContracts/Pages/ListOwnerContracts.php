<?php

namespace App\Filament\Resources\OwnerContracts\Pages;

use App\Filament\Resources\OwnerContracts\OwnerContractResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOwnerContracts extends ListRecords
{
    protected static string $resource = OwnerContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
