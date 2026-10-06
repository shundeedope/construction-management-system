<?php

test('the delivery README documents setup, operations, scope, and Filament mapping', function () {
    $readme = file_get_contents(base_path('README.md'));

    expect($readme)->not->toBeFalse()
        ->toContain('## Installation')
        ->toContain('## Environment')
        ->toContain('## Migrations and seeding')
        ->toContain('## Demo credentials')
        ->toContain('## Roles and workflows')
        ->toContain('## Queue, PDF, storage, and Drive')
        ->toContain('## Testing and quality')
        ->toContain('## Architecture overview')
        ->toContain('## How This Project Demonstrates Filament')
        ->toContain('## Limitations and excluded scope')
        ->toContain('php artisan migrate --seed')
        ->toContain('php artisan test --compact')
        ->toContain('QUEUE_CONNECTION')
        ->toContain('FILESYSTEM_DISK')
        ->toContain('Google Drive is intentionally disabled by default.');
});
