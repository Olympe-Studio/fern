<?php declare(strict_types=1);

use function Pest\version;

test('pest v4 is installed and running', function (): void {
    expect(version())
        ->toBeString()
        ->toStartWith('4.');
});
