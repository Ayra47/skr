<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guest_is_redirected_from_the_authenticated_home_page(): void
    {
        $this->get('/')->assertRedirectToRoute('login');
    }
}
