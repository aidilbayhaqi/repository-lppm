<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiEndpointPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_stays_json_for_javascript_clients(): void
    {
        $this->getJson(route('lookups', 'fakultas'))
            ->assertOk()
            ->assertJsonStructure(['data', 'has_more']);
    }

    public function test_opening_lookup_in_browser_shows_a_page_instead_of_raw_json(): void
    {
        $this->withHeader('Accept', 'text/html')
            ->get(route('lookups', 'fakultas'))
            ->assertRedirect(route('repository.search'));
    }
}
