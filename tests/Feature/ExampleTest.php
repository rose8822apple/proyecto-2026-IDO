<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/dashboard');

        $expectedDate = ucfirst(now('America/Caracas')->locale('es')->translatedFormat('l, j \\d\\e F'));

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee($expectedDate);
    }
}
