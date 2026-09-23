<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_frontend_origin_is_allowed(): void
    {
        config(['cors.allowed_origins' => ['https://city-lab.vercel.app']]);

        $this->withHeaders([
            'Origin' => 'https://city-lab.vercel.app',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/scenarios')
            ->assertHeader('Access-Control-Allow-Origin', 'https://city-lab.vercel.app');
    }

    public function test_frontend_url_env_drives_allowed_origin(): void
    {
        $this->assertSame([env('FRONTEND_URL', 'http://localhost:5173')], config('cors.allowed_origins'));
    }
}
