<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        // 204, no la vista "welcome" por defecto: api.cappixtremo.com no es
        // navegable, la raiz no debe delatar el framework (ver routes/web.php).
        $response->assertStatus(204);
    }
}
