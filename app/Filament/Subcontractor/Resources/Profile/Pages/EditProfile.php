<?php

namespace App\Filament\Subcontractor\Resources\Profile\Pages;

use App\Filament\Subcontractor\Resources\Profile\ProfileResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;

class EditProfile extends EditRecord
{
    protected static string $resource = ProfileResource::class;

    public function mount(int|string|null $record = null): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->vendor_id !== null, 403);

        parent::mount($user->vendor_id);
    }
}
