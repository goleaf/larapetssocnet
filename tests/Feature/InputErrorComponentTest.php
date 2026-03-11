<?php

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

uses(TestCase::class);

test('input error component renders safely without messages', function () {
    $html = Blade::render('<x-input-error />');

    expect(trim($html))->toBe('');
});
