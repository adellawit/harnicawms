<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // Homepage redirects to login for unauthenticated users
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
