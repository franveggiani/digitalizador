<?php

namespace Tests\Feature;

use Tests\TestCase;

final class HealthTest extends TestCase
{
    public function test_health_endpoint_exposes_stable_api_status(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }
}
