<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected from the application home page', function (): void {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
