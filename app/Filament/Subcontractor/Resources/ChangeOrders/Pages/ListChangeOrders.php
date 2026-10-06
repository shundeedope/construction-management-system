<?php

namespace App\Filament\Subcontractor\Resources\ChangeOrders\Pages;

use App\Filament\Subcontractor\Resources\ChangeOrders\ChangeOrderResource;
use Filament\Resources\Pages\ListRecords;

class ListChangeOrders extends ListRecords
{
    protected static string $resource = ChangeOrderResource::class;
}
