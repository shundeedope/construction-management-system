<?php

test('domain migrations avoid SQLite-only schema and SQL features', function () {
    $migrations = glob(database_path('migrations/2026_*.php'));
    $migrationSource = implode("\n", array_map(file_get_contents(...), $migrations));

    expect($migrationSource)
        ->not->toMatch('/sqlite_/i')
        ->not->toMatch('/pragma\s+/i')
        ->not->toMatch('/->enum\s*\(/i');
});
