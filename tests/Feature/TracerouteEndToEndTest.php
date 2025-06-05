<?php

namespace Tests\Feature;

use App\Services\MockTracerouteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TracerouteEndToEndTest extends TestCase
{
    /**
     * Test the critical user flow of performing a traceroute and viewing the results.
     */
    public function test_traceroute_end_to_end_flow(): void
    {
        // Bind the MockTracerouteService to the container
        $this->app->bind('App\Services\TracerouteService', function ($app) {
            return new MockTracerouteService();
        });

        // Step 1: Visit the main page
        $response = $this->get('/');
        $response->assertStatus(200);

        // Step 2: Perform a traceroute
        $response = $this->postJson('/api/traceroute', [
            'host' => 'example.com',
            'name' => 'Test Traceroute',
            'max_hops' => 30,
            'timeout' => 5
        ]);

        // Verify the response
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Test Traceroute',
                    'host' => 'example.com',
                ]
            ]);

        // Verify the structure of the response
        $response->assertJsonStructure([
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
        ]);

        // Verify that the first hop is localhost
        $response->assertJson([
            'data' => [
                'hops' => [
                    0 => [
                        'hop' => 1,
                        'hostname' => 'localhost',
                        'ip' => '127.0.0.1',
                    ]
                ]
            ]
        ]);
    }

    /**
     * Test the error handling flow when an invalid host is provided.
     */
    public function test_traceroute_error_handling_flow(): void
    {
        // Bind the MockTracerouteService to the container
        $this->app->bind('App\Services\TracerouteService', function ($app) {
            return new MockTracerouteService();
        });

        // Step 1: Visit the main page
        $response = $this->get('/');
        $response->assertStatus(200);

        // Step 2: Attempt to perform a traceroute with an empty host
        $response = $this->postJson('/api/traceroute', [
            'host' => '',
            'name' => 'Test Traceroute',
        ]);

        // Verify the error response
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['host']);
    }

    /**
     * Test the flow of performing multiple traceroutes.
     */
    public function test_multiple_traceroutes_flow(): void
    {
        // Bind the MockTracerouteService to the container
        $this->app->bind('App\Services\TracerouteService', function ($app) {
            return new MockTracerouteService();
        });

        // Step 1: Visit the main page
        $response = $this->get('/');
        $response->assertStatus(200);

        // Step 2: Perform first traceroute
        $response1 = $this->postJson('/api/traceroute', [
            'host' => 'example.com',
            'name' => 'First Traceroute',
        ]);

        // Verify the response
        $response1->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'First Traceroute',
                    'host' => 'example.com',
                ]
            ]);

        // Step 3: Perform second traceroute
        $response2 = $this->postJson('/api/traceroute', [
            'host' => 'google.com',
            'name' => 'Second Traceroute',
        ]);

        // Verify the response
        $response2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Second Traceroute',
                    'host' => 'google.com',
                ]
            ]);

        // Verify that the two responses have different IDs
        $this->assertNotEquals(
            $response1->json('data.id'),
            $response2->json('data.id')
        );
    }
}
