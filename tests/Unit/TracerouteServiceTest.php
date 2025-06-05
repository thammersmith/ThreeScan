<?php

namespace Tests\Unit;

use App\Services\MockTracerouteService;
use App\Services\TracerouteService;
use PHPUnit\Framework\TestCase;

class TracerouteServiceTest extends TestCase
{
    /**
     * Test that the trace method validates the host parameter
     */
    public function test_trace_validates_host(): void
    {
        $service = new MockTracerouteService();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Host cannot be empty');

        $service->trace('');
    }

    /**
     * Test that the trace method sets a default name if not provided
     */
    public function test_trace_sets_default_name(): void
    {
        $service = new MockTracerouteService();

        $result = $service->trace('example.com');

        $this->assertEquals('Trace to example.com', $result['name']);
    }

    /**
     * Test that the trace method uses the provided name
     */
    public function test_trace_uses_provided_name(): void
    {
        $service = new MockTracerouteService();

        $result = $service->trace('example.com', 'Custom Name');

        $this->assertEquals('Custom Name', $result['name']);
    }

    /**
     * Test that the trace method returns the expected structure
     */
    public function test_trace_returns_expected_structure(): void
    {
        $service = new MockTracerouteService();

        $result = $service->trace('example.com');

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('host', $result);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('hops', $result);

        $this->assertEquals('example.com', $result['host']);
        $this->assertIsArray($result['hops']);
        $this->assertNotEmpty($result['hops']);

        // Check the structure of the first hop
        $firstHop = $result['hops'][0];
        $this->assertArrayHasKey('hop', $firstHop);
        $this->assertArrayHasKey('ttl', $firstHop);
        $this->assertArrayHasKey('hostname', $firstHop);
        $this->assertArrayHasKey('ip', $firstHop);
        $this->assertArrayHasKey('pingTime', $firstHop);
    }

    /**
     * Test that the trace method handles options correctly
     */
    public function test_trace_handles_options(): void
    {
        $service = new MockTracerouteService();

        $options = [
            'max_hops' => 20,
            'timeout' => 3,
            'queries' => 2
        ];

        $result = $service->trace('example.com', 'Test with Options', $options);

        // Since we're using mock data, we can't directly test that the options
        // affected the trace, but we can verify that the method didn't fail
        $this->assertArrayHasKey('hops', $result);
        $this->assertNotEmpty($result['hops']);
    }

    /**
     * Test that the hop data has the expected structure
     */
    public function test_hop_data_structure(): void
    {
        $service = new MockTracerouteService();

        $result = $service->trace('example.com');

        foreach ($result['hops'] as $hop) {
            $this->assertArrayHasKey('hop', $hop);
            $this->assertArrayHasKey('ttl', $hop);
            $this->assertArrayHasKey('hostname', $hop);
            $this->assertArrayHasKey('ip', $hop);
            $this->assertArrayHasKey('pingTime', $hop);

            $this->assertIsInt($hop['hop']);
            $this->assertIsInt($hop['ttl']);
            $this->assertIsString($hop['hostname']);
            $this->assertIsString($hop['ip']);
            $this->assertIsInt($hop['pingTime']);
        }
    }

    /**
     * Test that the first hop is always the local machine
     */
    public function test_first_hop_is_local_machine(): void
    {
        $service = new MockTracerouteService();

        $result = $service->trace('example.com');

        $firstHop = $result['hops'][0];
        $this->assertEquals(1, $firstHop['hop']);
        $this->assertEquals('localhost', $firstHop['hostname']);
        $this->assertEquals('127.0.0.1', $firstHop['ip']);
    }
}
