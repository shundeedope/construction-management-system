<?php

namespace App\Filament\Resources\ChangeOrders\Pages;

use App\Filament\Resources\ChangeOrders\ChangeOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateChangeOrder extends CreateRecord
{
    protected static string $resource = ChangeOrderResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
