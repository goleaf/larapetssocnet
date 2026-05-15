<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

// use Illuminate\Foundation\Testing\RefreshDatabase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->get('/')->assertRedirect(route('explore.index'));
    }
}
