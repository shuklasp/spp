<?php

namespace SPP\Tests\Benchmarks;

/**
 * AsyncBenchmarkRunner
 * Standalone CLI benchmarking utility for testing SPP Async Coroutine Worker throughput.
 */
class AsyncBenchmarkRunner
{
    public static function run(string $url = 'http://127.0.0.1:8080/', int $totalRequests = 1000, int $concurrency = 20): void
    {
        echo "\033[32mINFO:\033[0m Starting SPP Async Benchmark against `{$url}`\n";
        echo "Concurrency: {$concurrency} | Total Requests: {$totalRequests}\n\n";

        $startTime = microtime(true);
        $completed = 0;
        $failed = 0;

        // If curl_multi is available, perform concurrent requests
        if (function_exists('curl_multi_init')) {
            $mh = curl_multi_init();
            $handles = [];
            $batches = ceil($totalRequests / $concurrency);

            for ($b = 0; $b < $batches; $b++) {
                $currentBatchSize = min($concurrency, $totalRequests - $completed);
                for ($i = 0; $i < $currentBatchSize; $i++) {
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-SPP-Benchmark: php-cli']);
                    curl_multi_add_handle($mh, $ch);
                    $handles[] = $ch;
                }

                $active = null;
                do {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);

                while ($active && $mrc == CURLM_OK) {
                    if (curl_multi_select($mh) != -1) {
                        do {
                            $mrc = curl_multi_exec($mh, $active);
                        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
                    }
                }

                foreach ($handles as $ch) {
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    if ($httpCode === 200) {
                        $completed++;
                    } else {
                        $failed++;
                    }
                    curl_multi_remove_handle($mh, $ch);
                    curl_close($ch);
                }
                $handles = [];
            }
            curl_multi_close($mh);
        } else {
            // Fallback sequential benchmarking
            for ($i = 0; $i < $totalRequests; $i++) {
                $context = stream_context_create(['http' => ['timeout' => 2, 'header' => "X-SPP-Benchmark: php-cli\r\n"]]);
                $res = @file_get_contents($url, false, $context);
                if ($res !== false) {
                    $completed++;
                } else {
                    $failed++;
                }
            }
        }

        $elapsed = microtime(true) - $startTime;
        $rps = $elapsed > 0 ? round(($completed + $failed) / $elapsed, 2) : 0;
        $avgLatency = ($completed + $failed) > 0 ? round(($elapsed / ($completed + $failed)) * 1000, 2) : 0;

        echo "\033[32mBENCHMARK RESULTS:\033[0m\n";
        echo "--------------------------------------------------\n";
        echo "Time taken for tests: " . round($elapsed, 4) . " seconds\n";
        echo "Complete requests:    {$completed}\n";
        echo "Failed requests:      {$failed}\n";
        echo "Requests per second:  {$rps} [#/sec] (mean)\n";
        echo "Time per request:     {$avgLatency} [ms] (mean)\n";
        echo "--------------------------------------------------\n";
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $url = $argv[1] ?? 'http://127.0.0.1:8080/';
    $requests = (int)($argv[2] ?? 1000);
    $concurrency = (int)($argv[3] ?? 20);
    AsyncBenchmarkRunner::run($url, $requests, $concurrency);
}
