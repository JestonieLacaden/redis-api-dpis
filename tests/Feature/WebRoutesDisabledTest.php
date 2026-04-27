<?php

namespace Tests\Feature;

use Tests\TestCase;

class WebRoutesDisabledTest extends TestCase
{
    public function test_root_path_returns_json_not_found(): void
    {
        $this->get('/')
            ->assertStatus(404)
            ->assertJson([
                'message' => 'Not Found',
            ]);
    }

    public function test_unknown_web_path_returns_json_not_found(): void
    {
        $this->get('/welcome')
            ->assertStatus(404)
            ->assertJson([
                'message' => 'Not Found',
            ]);
    }
}
