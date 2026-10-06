<?php

use Filament\Facades\Filament;

test('every admin panel navigation item has an icon', function () {
    Filament::setCurrentPanel('admin');
    $panel = Filament::getCurrentPanel();

    $items = collect([...$panel->getPages(), ...$panel->getResources()])
        ->filter(fn (string $class): bool => $class::shouldRegisterNavigation());

    expect($items)->not->toBeEmpty();

    $withoutIcon = $items
        ->filter(fn (string $class) => $class::getNavigationIcon() === null)
        ->values()
        ->all();

    expect($withoutIcon)->toBe([]);
});
