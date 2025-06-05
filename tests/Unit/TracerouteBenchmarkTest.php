<?php

namespace Tests\Unit;

use App\Services\MockTracerouteService;
use App\Services\TracerouteService;
use PHPUnit\Framework\TestCase;

class TracerouteBenchmarkTest extends TestCase
{
    /**
     * Benchmark the trace method of the TracerouteService.
     */
    public function test_benchmark_trace_method(): void
    {
        // Use the MockTracerouteService to avoid actual network operations
        $service = new MockTracerouteService();

        // Number of iterations for the benchmark
        $iterations = 100;

        // Start time
        $startTime = microtime(true);

        // Run the trace method multiple times
        for ($i = 0; $i < $iterations; $i++) {
            $result = $service->trace('example.com', 'Benchmark Test');
        }

        // End time
        $endTime = microtime(true);

        // Calculate total time and average time per operation
        $totalTime = $endTime - $startTime;
        $averageTime = $totalTime / $iterations;

        // Output benchmark results
        echo PHP_EOL . "Benchmark results for TracerouteService::trace method:" . PHP_EOL;
        echo "Total time for {$iterations} iterations: " . round($totalTime, 4) . " seconds" . PHP_EOL;
        echo "Average time per operation: " . round($averageTime * 1000, 4) . " milliseconds" . PHP_EOL;

        // Assert that the average time is below a reasonable threshold
        // This is a very generous threshold since we're using a mock service
        $this->assertLessThan(50, $averageTime * 1000, "Average trace operation time should be less than 50ms");
    }

    /**
     * Benchmark the memory usage of the TracerouteService.
     */
    public function test_benchmark_memory_usage(): void
    {
        // Use the MockTracerouteService to avoid actual network operations
        $service = new MockTracerouteService();

        // Number of iterations for the benchmark
        $iterations = 100;

        // Initial memory usage
        $initialMemory = memory_get_usage();

        // Run the trace method multiple times
        for ($i = 0; $i < $iterations; $i++) {
            $result = $service->trace('example.com', 'Memory Benchmark Test');
        }

        // Final memory usage
        $finalMemory = memory_get_usage();

        // Calculate memory increase
        $memoryIncrease = $finalMemory - $initialMemory;
        $averageMemoryPerOperation = $memoryIncrease / $iterations;

        // Output benchmark results
        echo PHP_EOL . "Memory usage benchmark for TracerouteService:" . PHP_EOL;
        echo "Initial memory usage: " . round($initialMemory / 1024 / 1024, 2) . " MB" . PHP_EOL;
        echo "Final memory usage: " . round($finalMemory / 1024 / 1024, 2) . " MB" . PHP_EOL;
        echo "Memory increase after {$iterations} iterations: " . round($memoryIncrease / 1024, 2) . " KB" . PHP_EOL;
        echo "Average memory increase per operation: " . round($averageMemoryPerOperation / 1024, 2) . " KB" . PHP_EOL;

        // Assert that the memory increase per operation is below a reasonable threshold
        // This is a very generous threshold since we're using a mock service
        $this->assertLessThan(100 * 1024, $averageMemoryPerOperation, "Average memory increase per operation should be less than 100KB");
    }

    /**
     * Benchmark the performance of parsing different output formats.
     */
    public function test_benchmark_parsing_performance(): void
    {
        // Use the MockTracerouteService to avoid actual network operations
        $service = new MockTracerouteService();

        // Number of iterations for the benchmark
        $iterations = 100;

        // Start time
        $startTime = microtime(true);

        // Run the trace method multiple times with different hosts
        for ($i = 0; $i < $iterations; $i++) {
            // Alternate between different hosts to potentially trigger different parsing paths
            if ($i % 2 === 0) {
                $result = $service->trace('example.com', 'Parse Benchmark 1');
            } else {
                $result = $service->trace('google.com', 'Parse Benchmark 2');
            }
        }

        // End time
        $endTime = microtime(true);

        // Calculate total time and average time per operation
        $totalTime = $endTime - $startTime;
        $averageTime = $totalTime / $iterations;

        // Output benchmark results
        echo PHP_EOL . "Parsing performance benchmark for TracerouteService:" . PHP_EOL;
        echo "Total time for {$iterations} iterations: " . round($totalTime, 4) . " seconds" . PHP_EOL;
        echo "Average time per operation: " . round($averageTime * 1000, 4) . " milliseconds" . PHP_EOL;

        // Assert that the average time is below a reasonable threshold
        $this->assertLessThan(50, $averageTime * 1000, "Average parsing operation time should be less than 50ms");
    }
}
