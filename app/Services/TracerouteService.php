<?php

namespace App\Services;

class TracerouteService
{
    /**
     * Execute a traceroute to the specified host
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

        // Build the traceroute command
        $command = $this->buildCommand($host, $options);

        // Execute the command
        $output = [];
        $returnCode = 0;

        // Use proc_open for better control and error handling
        $descriptorspec = [
            0 => ["pipe", "r"],  // stdin
            1 => ["pipe", "w"],  // stdout
            2 => ["pipe", "w"]   // stderr
        ];
        ini_set('max_execution_time', 300);
        $process = proc_open($command, $descriptorspec, $pipes);

        if (is_resource($process)) {
            // Close stdin as we don't need to write to it
            fclose($pipes[0]);

            // Read stdout
            while (!feof($pipes[1])) {
                $line = fgets($pipes[1]);
                if ($line !== false) {
                    $output[] = rtrim($line);
                }
            }

            // Read stderr
            $stderr = stream_get_contents($pipes[2]);

            // Close pipes
            fclose($pipes[1]);
            fclose($pipes[2]);

            // Get process exit code
            $returnCode = proc_close($process);

            // Check if the command was successful
            if ($returnCode !== 0) {
                throw new \Exception("Traceroute command failed with code {$returnCode}: " . ($stderr ?: implode("\n", $output)));
            }

            // If no output but command succeeded, something is wrong
            if (empty($output)) {
                throw new \Exception("Traceroute command produced no output");
            }
        } else {
            throw new \Exception("Failed to execute traceroute command");
        }

        // Parse the output
        $hops = $this->parseOutput($output);

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
     * Build the traceroute command with options
     *
     * @param string $host The hostname or IP address to trace
     * @param array $options Additional options for the traceroute command
     * @return string The complete command
     */
    private function buildCommand(string $host, array $options = []): string
    {
        // Default options
        $defaultOptions = [
            'max_hops' => 30,    // Maximum number of hops
            'timeout' => 5,      // Timeout in seconds
            'queries' => 3,      // Number of queries per hop
        ];

        // Merge with user options
        $options = array_merge($defaultOptions, $options);

        // Determine if the host is an IPv6 address
        $isIPv6 = strpos($host, ':') !== false;

        // Determine the command based on OS
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        if ($isWindows) {
            // Windows tracert command
            if ($isIPv6) {
                // Windows tracert command for IPv6
                $command = sprintf(
                    'tracert -6 -h %d -w %d %s',
                    $options['max_hops'],
                    $options['timeout'] * 1000, // Windows uses milliseconds
                    escapeshellarg($host)
                );
            } else {
                // Windows tracert command for IPv4
                $command = sprintf(
                    'tracert -h %d -w %d %s',
                    $options['max_hops'],
                    $options['timeout'] * 1000, // Windows uses milliseconds
                    escapeshellarg($host)
                );
            }
        } else {
            // Unix/Linux traceroute command
            if ($isIPv6) {
                // Unix/Linux traceroute6 command for IPv6
                $command = sprintf(
                    'traceroute6 -m %d -w %d -q %d %s',
                    $options['max_hops'],
                    $options['timeout'],
                    $options['queries'],
                    escapeshellarg($host)
                );
            } else {
                // Unix/Linux traceroute command for IPv4
                $command = sprintf(
                    'traceroute -m %d -w %d -q %d %s',
                    $options['max_hops'],
                    $options['timeout'],
                    $options['queries'],
                    escapeshellarg($host)
                );
            }
        }

        return $command;
    }

    /**
     * Parse the traceroute command output
     *
     * @param array $output The command output lines
     * @return array The parsed hops
     */
    private function parseOutput(array $output): array
    {
        $hops = [];
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        // Debug: Log the raw output
        error_log("Traceroute raw output: " . implode("\n", $output));

        // Skip the header lines
        $startLine = $isWindows ? 4 : 1;

        // Adjust startLine if needed based on actual output
        if (count($output) > 0) {
            // For Windows, look for the line that starts with "Tracing route to"
            if ($isWindows) {
                for ($i = 0; $i < min(5, count($output)); $i++) {
                    if (strpos($output[$i], 'Tracing route to') !== false) {
                        $startLine = $i + 4; // Skip 4 lines after the "Tracing route to" line
                        break;
                    }
                }
            }
            // For Unix/Linux, look for the line that contains the hostname and IP
            else {
                for ($i = 0; $i < min(3, count($output)); $i++) {
                    if (preg_match('/traceroute to/', $output[$i])) {
                        $startLine = $i + 1;
                        break;
                    }
                }
            }
        }

        for ($i = $startLine; $i < count($output); $i++) {
            $line = trim($output[$i]);

            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // Skip lines that don't start with a number (hop number)
            if (!preg_match('/^\s*\d+/', $line)) {
                continue;
            }

            // Parse the line based on OS format
            if ($isWindows) {
                $hop = $this->parseWindowsLine($line);
            } else {
                $hop = $this->parseUnixLine($line);
            }

            if ($hop) {
                $hops[] = $hop;
            }
        }

        // Debug: Log the parsed hops
        error_log("Parsed hops: " . json_encode($hops));

        // Ensure hop 1 is always the local machine (127.0.0.1)
        $hasHop1 = false;
        foreach ($hops as &$hop) {
            if ($hop['hop'] === 1) {
                $hop['hostname'] = 'localhost';
                $hop['ip'] = '127.0.0.1';
                $hasHop1 = true;
                break;
            }
        }

        // If hop 1 doesn't exist, add it
        if (!$hasHop1 && !empty($hops)) {
            // Create a new hop 1 entry
            $hop1 = [
                'hop' => 1,
                'ttl' => 1,
                'hostname' => 'localhost',
                'ip' => '127.0.0.1',
                'pingTime' => 0 // Local machine ping time is essentially 0
            ];

            // Insert at the beginning of the array
            array_unshift($hops, $hop1);

            // Adjust hop numbers if needed
            if (count($hops) > 1 && $hops[1]['hop'] === 1) {
                // If the original first hop was also 1, we need to increment all subsequent hop numbers
                for ($i = 1; $i < count($hops); $i++) {
                    $hops[$i]['hop'] += 1;
                    $hops[$i]['ttl'] += 1;
                }
            }
        }

        return $hops;
    }

    /**
     * Parse a line from Windows tracert output
     *
     * @param string $line The line to parse
     * @return array|null The parsed hop data or null if parsing failed
     */
    private function parseWindowsLine(string $line): ?array
    {
        // Windows tracert format examples:
        // 1    <1 ms    <1 ms    <1 ms  router.local [192.168.1.1]
        // 2     3 ms     2 ms     2 ms  router.local [192.168.1.254]
        // 3    15 ms    14 ms    15 ms  isp-gateway.net [203.0.113.1]
        // 4     *        *        *     Request timed out.
        // 5    10 ms     9 ms    11 ms  10.0.0.1
        // 6    12 ms    11 ms    12 ms  [10.0.0.2]
        // 7    14 ms    13 ms    14 ms  some-router.example.com
        // 8     9 ms     9 ms     8 ms  * h-4-1.core.rantoul.il.metrocomm.net [2607:5380:8000::19]
        // 5    10 ms     6 ms     6 ms  h-4-1.core.rantoul.il.metrocomm.net [2607:5380:8000::19]

        // Debug the line being parsed
        error_log("Parsing Windows line: " . $line);

        // Skip trace complete line
        if (strpos($line, 'Trace complete') !== false) {
            return null;
        }

        // Extract hop number
        if (!preg_match('/^\s*(\d+)/', $line, $hopMatch)) {
            error_log("Failed to extract hop number from line: " . $line);
            return null;
        }
        $hopNumber = (int)$hopMatch[1];

        // Check for complete timeout or unreachable
        if (strpos($line, 'Request timed out') !== false ||
            (strpos($line, '*') !== false && !preg_match('/\d+\s*ms/', $line))) {
            error_log("Complete timeout detected for hop: " . $hopNumber);
            return [
                'hop' => $hopNumber,
                'ttl' => $hopNumber,
                'hostname' => 'Timeout',
                'ip' => '*',
                'pingTime' => 0
            ];
        }

        // Check for partial timeout (some asterisks, some responses)
        // In this case, we want to extract the hostname/IP from the successful responses
        $hasTimeout = strpos($line, '*') !== false;
        $hasResponse = preg_match('/\d+\s*ms/', $line);

        if ($hasTimeout && $hasResponse) {
            error_log("Partial timeout detected for hop: " . $hopNumber);
            // Continue processing to extract hostname/IP from the successful responses
        }

        // Extract ping times
        preg_match_all('/(\d+|<\d+)\s*ms/', $line, $pingMatches);
        $pingTimes = [];
        foreach ($pingMatches[1] as $ping) {
            if (strpos($ping, '<') === 0) {
                // Handle "<1 ms" case
                $pingTimes[] = 1;
            } else {
                $pingTimes[] = (int)$ping;
            }
        }

        // Calculate average ping time
        $avgPing = count($pingTimes) > 0 ? array_sum($pingTimes) / count($pingTimes) : 0;

        // First, remove the hop number and ping times from the line to get just the hostname/IP part
        $cleanLine = preg_replace('/^\s*\d+\s+/', '', $line); // Remove hop number
        $cleanLine = preg_replace('/(\d+|<\d+)\s*ms\s+/', '', $cleanLine); // Remove ping times
        $cleanLine = trim($cleanLine);

        error_log("Cleaned line for hostname/IP extraction: " . $cleanLine);

        // Extract hostname and IP
        $hostname = 'Unknown';
        $ip = 'Unknown';

        // Try different patterns to extract hostname and IP from the cleaned line

        // Pattern for IPv6 address with hostname
        if (preg_match('/([\w\.-]+(?:\s+[\w\.-]+)*)\s+\[([0-9a-fA-F:]+(?:\.\d{1,3}){0,3})\]/', $cleanLine, $ipv6Match)) {
            $hostname = $ipv6Match[1];
            $ip = $ipv6Match[2];
            error_log("IPv6 with hostname pattern matched: hostname={$hostname}, ip={$ip}");
        }
        // Pattern for IPv6 address only
        elseif (preg_match('/\[([0-9a-fA-F:]+(?:\.\d{1,3}){0,3})\]/', $cleanLine, $ipv6OnlyMatch)) {
            $hostname = "IPv6-Host";
            $ip = $ipv6OnlyMatch[1];
            error_log("IPv6 only pattern matched: ip={$ip}");
        }
        // Pattern for line with asterisk and hostname/IP
        elseif (preg_match('/\*\s+([\w\.-]+(?:\s+[\w\.-]+)*)\s+\[([\d\.:a-fA-F]+)\]/', $cleanLine, $asteriskMatch)) {
            $hostname = $asteriskMatch[1];
            $ip = $asteriskMatch[2];
            error_log("Asterisk with hostname/IP pattern matched: hostname={$hostname}, ip={$ip}");
        }
        // Pattern for line where hostname is an asterisk followed by IPv6 address
        elseif (preg_match('/\*\s+([\d\.:a-fA-F]+)/', $cleanLine, $asteriskIpv6Match)) {
            $hostname = "*";
            $ip = $asteriskIpv6Match[1];
            error_log("Asterisk with IPv6 pattern matched: hostname={$hostname}, ip={$ip}");
        }
        // Pattern 1: hostname [ip]
        elseif (preg_match('/([\w\.-]+(?:\s+[\w\.-]+)*)\s+\[([\d\.]+)\]/', $cleanLine, $hostMatch)) {
            $hostname = $hostMatch[1];
            $ip = $hostMatch[2];
            error_log("Pattern 1 matched: hostname={$hostname}, ip={$ip}");
        }
        // Pattern 2: [ip] only
        elseif (preg_match('/\[([\d\.]+)\]/', $cleanLine, $ipMatch)) {
            $hostname = $ipMatch[1]; // Use IP as hostname
            $ip = $ipMatch[1];
            error_log("Pattern 2 matched: ip={$ip}");
        }
        // Pattern 3: hostname (ip) - IPv4
        elseif (preg_match('/([\w\.-]+(?:\s+[\w\.-]+)*)\s+\(([\d\.]+)\)/', $cleanLine, $hostParenMatch)) {
            $hostname = $hostParenMatch[1];
            $ip = $hostParenMatch[2];
            error_log("Pattern 3 matched: hostname={$hostname}, ip={$ip}");
        }
        // Pattern 3b: hostname (ipv6) - IPv6 with parentheses
        elseif (preg_match('/([\w\.-]+(?:\s+[\w\.-]+)*)\s+\(([0-9a-fA-F:]+(?:\.\d{1,3}){0,3})\)/', $cleanLine, $hostIPv6ParenMatch)) {
            $hostname = $hostIPv6ParenMatch[1];
            $ip = $hostIPv6ParenMatch[2];
            error_log("Pattern 3b matched: hostname={$hostname}, ip={$ip}");
        }
        // Pattern 4: ip only (without brackets) - IPv4
        elseif (preg_match('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})(?!\S)/', $cleanLine, $ipOnlyMatch)) {
            $hostname = $ipOnlyMatch[1];
            $ip = $ipOnlyMatch[1];
            error_log("Pattern 4 matched: ip={$ip}");
        }
        // Pattern 4b: ipv6 only (without brackets or parentheses)
        elseif (preg_match('/([0-9a-fA-F:]+(?:\.\d{1,3}){0,3})(?!\S)/', $cleanLine, $ipv6OnlyMatch)) {
            $hostname = "IPv6-Host";
            $ip = $ipv6OnlyMatch[1];
            error_log("Pattern 4b matched: ip={$ip}");
        }
        // Pattern 5: hostname only
        elseif (preg_match('/([\w\.-]+(?:\s+[\w\.-]+)*)$/', $cleanLine, $hostnameMatch)) {
            $hostname = $hostnameMatch[1];
            $ip = 'Unknown';
            error_log("Pattern 5 matched: hostname={$hostname}");
        }
        // If no pattern matched, treat the entire cleaned line as hostname if it's not empty
        elseif (!empty($cleanLine) && $cleanLine !== '*') {
            $hostname = $cleanLine;
            $ip = 'Unknown';
            error_log("No pattern matched, using entire cleaned line as hostname: {$hostname}");
        }

        error_log("Final parsed data for hop {$hopNumber}: hostname={$hostname}, ip={$ip}, pingTime={$avgPing}");

        // Validate hostname - if it's just a single digit or very short, it's likely not a real hostname
        if (preg_match('/^\d+$/', $hostname) || strlen($hostname) <= 2) {
            error_log("Invalid hostname detected: {$hostname}. Setting to 'Unknown'");
            $hostname = 'Unknown';
        }

        // According to the issue description, the IP should always be included in the traceroute output
        // If we couldn't extract the IP using our patterns, we need to try harder
        if ($ip === 'Unknown') {
            // Look for any IPv4 or IPv6 address in the original line
            if (preg_match('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/', $line, $ipv4Match)) {
                $ip = $ipv4Match[1];
                error_log("Found IPv4 in original line: {$ip}");
            } elseif (preg_match('/([0-9a-fA-F:]+(?:\.\d{1,3}){0,3})/', $line, $ipv6Match)) {
                $ip = $ipv6Match[1];
                error_log("Found IPv6 in original line: {$ip}");
            } elseif (strpos($line, '*') !== false) {
                // If there are asterisks, it's a timeout, use the asterisk as the IP
                $ip = '*';
                error_log("Using * as IP for timeout");
            }
        }

        return [
            'hop' => $hopNumber,
            'ttl' => $hopNumber,
            'hostname' => $hostname,
            'ip' => $ip,
            'pingTime' => round($avgPing)
        ];
    }

    /**
     * Parse a line from Unix/Linux traceroute output
     *
     * @param string $line The line to parse
     * @return array|null The parsed hop data or null if parsing failed
     */
    private function parseUnixLine(string $line): ?array
    {
        // Unix traceroute format examples:
        // 1  router.local (192.168.1.1)  0.123 ms  0.456 ms  0.789 ms
        // 2  router.local (192.168.1.254)  1.234 ms  1.567 ms  1.890 ms
        // 3  * * *
        // 4  10.0.0.1  5.678 ms  5.901 ms  6.123 ms
        // 5  * 10.0.0.2  7.890 ms  8.012 ms
        // 6  some-router.example.com  9.345 ms  9.678 ms  10.012 ms
        // 7  *     h-4-1.core.rantoul.il.metrocomm.net [2607:5380:8000::19]  9.345 ms  9.678 ms

        // Debug the line being parsed
        error_log("Parsing Unix line: " . $line);

        // Extract hop number
        if (!preg_match('/^\s*(\d+)/', $line, $hopMatch)) {
            error_log("Failed to extract hop number from line: " . $line);
            return null;
        }
        $hopNumber = (int)$hopMatch[1];

        // Check for complete timeout (all asterisks)
        if (preg_match('/^\s*\d+\s+\*\s+\*\s+\*/', $line)) {
            error_log("Complete timeout detected for hop: " . $hopNumber);
            return [
                'hop' => $hopNumber,
                'ttl' => $hopNumber,
                'hostname' => 'Timeout',
                'ip' => '*',
                'pingTime' => 0
            ];
        }

        // Check for partial timeout (some asterisks, some responses)
        // In this case, we want to extract the hostname/IP from the successful responses
        $hasTimeout = strpos($line, '*') !== false;
        $hasResponse = preg_match('/\d+\.\d+\s*ms/', $line);

        if ($hasTimeout && $hasResponse) {
            error_log("Partial timeout detected for hop: " . $hopNumber);
            // Continue processing to extract hostname/IP from the successful responses
        }

        // First, remove the hop number and ping times from the line to get just the hostname/IP part
        $cleanLine = preg_replace('/^\s*\d+\s+/', '', $line); // Remove hop number
        $cleanLine = preg_replace('/\s+\d+\.\d+\s*ms/', '', $cleanLine); // Remove ping times
        $cleanLine = trim($cleanLine);

        error_log("Cleaned line for hostname/IP extraction: " . $cleanLine);

        // Extract hostname and IP
        $hostname = 'Unknown';
        $ip = 'Unknown';

        // Try different patterns to extract hostname and IP from the cleaned line

        // Pattern for IPv6 address with hostname
        if (preg_match('/([\w\.-]+(?:\s+[\w\.-]+)*)\s+\[([0-9a-fA-F:]+(?:\.\d{1,3}){0,3})\]/', $cleanLine, $ipv6Match)) {
            $hostname = $ipv6Match[1];
            $ip = $ipv6Match[2];
            error_log("IPv6 with hostname pattern matched: hostname={$hostname}, ip={$ip}");
        }
        // Pattern for IPv6 address only
        elseif (preg_match('/\[([0-9a-fA-F:]+(?:\.\d{1,3}){0,3})\]/', $cleanLine, $ipv6OnlyMatch)) {
            $hostname = "IPv6-Host";
            $ip = $ipv6OnlyMatch[1];
            error_log("IPv6 only pattern matched: ip={$ip}");
        }
        // Pattern for line with asterisk and hostname/IP
        elseif (preg_match('/\*\s+([\w\.-]+(?:\s+[\w\.-]+)*)\s+\[([\d\.:a-fA-F]+)\]/', $cleanLine, $asteriskMatch)) {
            $hostname = $asteriskMatch[1];
            $ip = $asteriskMatch[2];
            error_log("Asterisk with hostname/IP pattern matched: hostname={$hostname}, ip={$ip}");
        }
        // Pattern for line where hostname is an asterisk followed by IPv6 address
        elseif (preg_match('/\*\s+([\d\.:a-fA-F]+)/', $cleanLine, $asteriskIpv6Match)) {
            $hostname = "*";
            $ip = $asteriskIpv6Match[1];
            error_log("Asterisk with IPv6 pattern matched: hostname={$hostname}, ip={$ip}");
        }
        // Pattern 1: hostname (ip) - IPv4
        elseif (preg_match('/([\w\.-]+(?:\s+[\w\.-]+)*)\s+\(([\d\.]+)\)/', $cleanLine, $hostMatch)) {
            $hostname = $hostMatch[1];
            $ip = $hostMatch[2];
            error_log("Pattern 1 matched: hostname={$hostname}, ip={$ip}");
        }
        // Pattern 1b: hostname (ipv6) - IPv6 with parentheses
        elseif (preg_match('/([\w\.-]+(?:\s+[\w\.-]+)*)\s+\(([0-9a-fA-F:]+(?:\.\d{1,3}){0,3})\)/', $cleanLine, $hostIPv6Match)) {
            $hostname = $hostIPv6Match[1];
            $ip = $hostIPv6Match[2];
            error_log("Pattern 1b matched: hostname={$hostname}, ip={$ip}");
        }
        // Pattern 2: (ip) only - IPv4
        elseif (preg_match('/\(([\d\.]+)\)/', $cleanLine, $ipMatch)) {
            $hostname = $ipMatch[1]; // Use IP as hostname
            $ip = $ipMatch[1];
            error_log("Pattern 2 matched: ip={$ip}");
        }
        // Pattern 2b: (ipv6) only - IPv6 with parentheses
        elseif (preg_match('/\(([0-9a-fA-F:]+(?:\.\d{1,3}){0,3})\)/', $cleanLine, $ipv6ParenMatch)) {
            $hostname = "IPv6-Host";
            $ip = $ipv6ParenMatch[1];
            error_log("Pattern 2b matched: ip={$ip}");
        }
        // Pattern 3: hostname [ip]
        elseif (preg_match('/([\w\.-]+(?:\s+[\w\.-]+)*)\s+\[([\d\.]+)\]/', $cleanLine, $hostBracketMatch)) {
            $hostname = $hostBracketMatch[1];
            $ip = $hostBracketMatch[2];
            error_log("Pattern 3 matched: hostname={$hostname}, ip={$ip}");
        }
        // Pattern 4: ip only (without parentheses) - IPv4
        elseif (preg_match('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/', $cleanLine, $ipOnlyMatch)) {
            $hostname = $ipOnlyMatch[1];
            $ip = $ipOnlyMatch[1];
            error_log("Pattern 4 matched: ip={$ip}");
        }
        // Pattern 4b: ipv6 only (without brackets or parentheses)
        elseif (preg_match('/([0-9a-fA-F:]+(?:\.\d{1,3}){0,3})(?!\S)/', $cleanLine, $ipv6OnlyMatch)) {
            $hostname = "IPv6-Host";
            $ip = $ipv6OnlyMatch[1];
            error_log("Pattern 4b matched: ip={$ip}");
        }
        // Pattern 5: hostname only
        elseif (preg_match('/([\w\.-]+(?:\s+[\w\.-]+)*)$/', $cleanLine, $hostnameMatch)) {
            $hostname = $hostnameMatch[1];
            $ip = 'Unknown';
            error_log("Pattern 5 matched: hostname={$hostname}");
        }
        // Check for IPv6 address in the cleaned line
        elseif (preg_match('/([0-9a-fA-F:]+(?:\.\d{1,3}){0,3})/', $cleanLine, $ipv6CleanMatch)) {
            $hostname = "IPv6-Host";
            $ip = $ipv6CleanMatch[1];
            error_log("IPv6 clean match: ip={$ip}");
        }
        // If no pattern matched, treat the entire cleaned line as hostname if it's not empty
        elseif (!empty($cleanLine) && $cleanLine !== '*') {
            $hostname = $cleanLine;
            $ip = 'Unknown';
            error_log("No pattern matched, using entire cleaned line as hostname: {$hostname}");
        }

        // Extract ping times
        preg_match_all('/(\d+\.\d+)\s*ms/', $line, $pingMatches);
        $pingTimes = [];
        foreach ($pingMatches[1] as $ping) {
            $pingTimes[] = (float)$ping;
        }

        // Calculate average ping time
        $avgPing = count($pingTimes) > 0 ? array_sum($pingTimes) / count($pingTimes) : 0;

        error_log("Final parsed data for hop {$hopNumber}: hostname={$hostname}, ip={$ip}, pingTime={$avgPing}");

        // Validate hostname - if it's just a single digit or very short, it's likely not a real hostname
        if (preg_match('/^\d+$/', $hostname) || strlen($hostname) <= 2) {
            error_log("Invalid hostname detected: {$hostname}. Setting to 'Unknown'");
            $hostname = 'Unknown';
        }

        // According to the issue description, the IP should always be included in the traceroute output
        // If we couldn't extract the IP using our patterns, we need to try harder
        if ($ip === 'Unknown') {
            // Look for any IPv4 or IPv6 address in the original line
            if (preg_match('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/', $line, $ipv4Match)) {
                $ip = $ipv4Match[1];
                error_log("Found IPv4 in original line: {$ip}");
            } elseif (preg_match('/([0-9a-fA-F:]+(?:\.\d{1,3}){0,3})/', $line, $ipv6Match)) {
                $ip = $ipv6Match[1];
                error_log("Found IPv6 in original line: {$ip}");
            } elseif (strpos($line, '*') !== false) {
                // If there are asterisks, it's a timeout, use the asterisk as the IP
                $ip = '*';
                error_log("Using * as IP for timeout");
            }
        }

        return [
            'hop' => $hopNumber,
            'ttl' => $hopNumber,
            'hostname' => $hostname,
            'ip' => $ip,
            'pingTime' => round($avgPing)
        ];
    }
}
