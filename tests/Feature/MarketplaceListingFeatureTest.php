<?php

uses(Tests\TestCase::class);

it('loads the homepage', function (): void {
    $response = $this->get('/');

    $response->assertRedirect(route('explore.index'));
});
