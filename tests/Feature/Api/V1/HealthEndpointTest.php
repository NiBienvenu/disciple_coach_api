<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_endpoint_is_public_and_returns_standard_success_payload(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'status',
                    'app',
                    'environment',
                    'cache_store',
                ],
                'message',
                'meta',
            ]);

        $this->assertNull($response->json('message'));
        $this->assertSame('array', $response->json('data.cache_store'));
    }

    public function test_unknown_api_route_returns_standard_error_payload(): void
    {
        $response = $this->getJson('/api/v1/does-not-exist');

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Resource not found.')
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ]);
    }

    public function test_laravel_health_endpoint_still_works(): void
    {
        $this->get('/up')->assertOk();
    }
}
