<?php

namespace App\Filament\Resources\Subcontracts\Pages;

use App\Filament\Resources\Subcontracts\SubcontractResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSubcontracts extends ListRecords
{
    protected static string $resource = SubcontractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
