<?php

namespace App\Filament\Subcontractor\Resources\Contracts\Pages;

use App\Filament\Subcontractor\Resources\Contracts\ContractResource;
use Filament\Resources\Pages\ViewRecord;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;
}
