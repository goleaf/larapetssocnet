<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected from the pet create wizard page', function (): void {
    $response = $this->get(route('pets.create'));

    $response->assertRedirect(route('login'));
});
