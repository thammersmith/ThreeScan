<?php

namespace App\Services;

class MockTracerouteService extends TracerouteService
{
    /**
     * Sample Windows traceroute output for testing
     *
     * @var array
     */
    protected $windowsSampleOutput = [
        "Tracing route to google.com [142.250.190.78]",
        "over a maximum of 30 hops:",
        "",
        "  1    <1 ms    <1 ms    <1 ms  router.local [192.168.1.1]",
        "  2     3 ms     2 ms     2 ms  isp-gateway.net [203.0.113.1]",
        "  3    15 ms    14 ms    15 ms  core1.example.net [198.51.100.1]",
        "  4     *        *        *     Request timed out.",
        "  5    10 ms     9 ms    11 ms  10.0.0.1",
        "  6    12 ms    11 ms    12 ms  [10.0.0.2]",
        "  7    14 ms    13 ms    14 ms  some-router.example.com",
        "  8    20 ms    19 ms    20 ms  142.250.190.78",
        "",
        "Trace complete."
    ];

    /**
     * Sample Unix traceroute output for testing
     *
     * @var array
     */
    protected $unixSampleOutput = [
        "traceroute to google.com (142.250.190.78), 30 hops max, 60 byte packets",
        " 1  router.local (192.168.1.1)  0.123 ms  0.456 ms  0.789 ms",
        " 2  isp-gateway.net (203.0.113.1)  1.234 ms  1.567 ms  1.890 ms",
        " 3  core1.example.net (198.51.100.1)  15.123 ms  14.456 ms  15.789 ms",
        " 4  * * *",
        " 5  10.0.0.1  10.123 ms  9.456 ms  11.789 ms",
        " 6  10.0.0.2  12.123 ms  11.456 ms  12.789 ms",
        " 7  some-router.example.com  14.123 ms  13.456 ms  14.789 ms",
        " 8  142.250.190.78  20.123 ms  19.456 ms  20.789 ms"
    ];

    /**
     * Override the trace method to return mock data instead of executing a real traceroute
     *
     * @param string $host The hostname or IP address to trace
     * @param string $name Optional name for this traceroute
     * @param array $options Additional options for the traceroute command
     * @return array The parsed traceroute results
     * @throws \Exception If the traceroute command fails
     */
    public function trace(string $host, string $name = '', array $options = []): array
    {
        // Validate the host
        if (empty($host)) {
            throw new \InvalidArgumentException('Host cannot be empty');
        }

        // Set default name if not provided
        if (empty($name)) {
            $name = "Trace to {$host}";
        }

        // Generate mock hop data
        $hops = $this->getMockHopData();

        // Return the formatted result
        return [
            'id' => uniqid('trace_'),
            'name' => $name,
            'host' => $host,
            'timestamp' => time(),
            'hops' => $hops
        ];
    }

    /**
     * Override the buildCommand method to prevent actual command execution
     *
     * @param string $host The hostname or IP address to trace
     * @param array $options Additional options for the traceroute command
     * @return string The complete command
     */
    protected function buildCommand(string $host, array $options = []): string
    {
        // Return a dummy command string
        return "mock_traceroute_command";
    }

    /**
     * Generate mock hop data for testing
     *
     * @return array The mock hop data
     */
    protected function getMockHopData(): array
    {
        return [
            [
                'hop' => 1,
                'ttl' => 1,
                'hostname' => 'localhost',
                'ip' => '127.0.0.1',
                'pingTime' => 0
            ],
            [
                'hop' => 2,
                'ttl' => 2,
                'hostname' => 'router.local',
                'ip' => '192.168.1.1',
                'pingTime' => 1
            ],
            [
                'hop' => 3,
                'ttl' => 3,
                'hostname' => 'isp-gateway.net',
                'ip' => '203.0.113.1',
                'pingTime' => 3
            ],
            [
                'hop' => 4,
                'ttl' => 4,
                'hostname' => 'core1.example.net',
                'ip' => '198.51.100.1',
                'pingTime' => 15
            ],
            [
                'hop' => 5,
                'ttl' => 5,
                'hostname' => 'Timeout',
                'ip' => '*',
                'pingTime' => 0
            ],
            [
                'hop' => 6,
                'ttl' => 6,
                'hostname' => 'Unknown',
                'ip' => '10.0.0.1',
                'pingTime' => 10
            ],
            [
                'hop' => 7,
                'ttl' => 7,
                'hostname' => '10.0.0.2',
                'ip' => '10.0.0.2',
                'pingTime' => 12
            ],
            [
                'hop' => 8,
                'ttl' => 8,
                'hostname' => 'some-router.example.com',
                'ip' => '10.0.0.3',
                'pingTime' => 14
            ],
            [
                'hop' => 9,
                'ttl' => 9,
                'hostname' => 'destination-server',
                'ip' => '142.250.190.78',
                'pingTime' => 20
            ]
        ];
    }
}
