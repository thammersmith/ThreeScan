<?php

namespace Tests\Feature;

use App\Services\MockTracerouteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TracerouteApiTest extends TestCase
{
    /**
     * Test that the traceroute API endpoint returns a successful response with valid input.
     */
    public function test_traceroute_api_returns_successful_response(): void
    {
        // Bind the MockTracerouteService to the container
        $this->app->bind('App\Services\TracerouteService', function ($app) {
            return new MockTracerouteService();
        });

        $response = $this->postJson('/api/traceroute', [
            'host' => 'example.com',
            'name' => 'Test Traceroute',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'host',
                    'timestamp',
                    'hops' => [
                        '*' => [
                            'hop',
                            'ttl',
                            'hostname',
                            'ip',
                            'pingTime'
                        ]
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Test Traceroute',
                    'host' => 'example.com',
                ]
            ]);
    }

    /**
     * Test that the traceroute API endpoint validates the host parameter.
     */
    public function test_traceroute_api_validates_host(): void
    {
        $response = $this->postJson('/api/traceroute', [
            'name' => 'Test Traceroute',
            // Missing host parameter
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['host']);
    }

    /**
     * Test that the traceroute API endpoint validates the max_hops parameter.
     */
    public function test_traceroute_api_validates_max_hops(): void
    {
        $response = $this->postJson('/api/traceroute', [
            'host' => 'example.com',
            'max_hops' => 0, // Invalid value
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['max_hops']);

        $response = $this->postJson('/api/traceroute', [
            'host' => 'example.com',
            'max_hops' => 65, // Invalid value
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['max_hops']);
    }

    /**
     * Test that the traceroute API endpoint validates the timeout parameter.
     */
    public function test_traceroute_api_validates_timeout(): void
    {
        $response = $this->postJson('/api/traceroute', [
            'host' => 'example.com',
            'timeout' => 0, // Invalid value
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['timeout']);

        $response = $this->postJson('/api/traceroute', [
            'host' => 'example.com',
            'timeout' => 31, // Invalid value
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['timeout']);
    }

    /**
     * Test that the traceroute API endpoint validates the queries parameter.
     */
    public function test_traceroute_api_validates_queries(): void
    {
        $response = $this->postJson('/api/traceroute', [
            'host' => 'example.com',
            'queries' => 0, // Invalid value
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['queries']);

        $response = $this->postJson('/api/traceroute', [
            'host' => 'example.com',
            'queries' => 11, // Invalid value
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['queries']);
    }

    /**
     * Test that the traceroute API endpoint handles options correctly.
     */
    public function test_traceroute_api_handles_options(): void
    {
        // Bind the MockTracerouteService to the container
        $this->app->bind('App\Services\TracerouteService', function ($app) {
            return new MockTracerouteService();
        });

        $response = $this->postJson('/api/traceroute', [
            'host' => 'example.com',
            'name' => 'Test with Options',
            'max_hops' => 20,
            'timeout' => 3,
            'queries' => 2
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Test with Options',
                    'host' => 'example.com',
                ]
            ]);
    }
}
