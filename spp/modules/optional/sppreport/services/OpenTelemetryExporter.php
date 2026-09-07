<?php

namespace SPPMod\SPPReport\Services;

/**
 * OpenTelemetryExporter
 * Formats active W3C trace spans into standard OTLP/JSON payload structures and
 * dispatches them asynchronously to configured OTEL collector endpoints (Jaeger, Zipkin, etc.).
 */
class OpenTelemetryExporter
{
    private string $endpoint;
    private array $spans = [];

    public function __construct(?string $endpoint = null)
    {
        $this->endpoint = $endpoint ?: \SPP\Module::getConfig('otel_collector_endpoint', 'sppreport') ?: 'http://127.0.0.1:4318/v1/traces';
    }

    /**
     * Add a span to the batch.
     */
    public function addSpan(string $name, string $traceId, string $spanId, string $parentSpanId = '', array $attributes = [], int $startTime = 0, int $endTime = 0): void
    {
        $this->spans[] = [
            'name' => $name,
            'trace_id' => $traceId,
            'span_id' => $spanId,
            'parent_span_id' => $parentSpanId,
            'attributes' => $attributes,
            'start_time_unix_nano' => ($startTime ?: microtime(true)) * 1000000000,
            'end_time_unix_nano' => ($endTime ?: microtime(true)) * 1000000000,
        ];
    }

    /**
     * Build the standard OTLP JSON payload structure.
     */
    public function buildPayload(): array
    {
        $resourceSpans = [
            [
                'resource' => [
                    'attributes' => [
                        ['key' => 'service.name', 'value' => ['stringValue' => \SPP\Scheduler::getContext() ?: 'spp_enterprise_service']],
                        ['key' => 'telemetry.sdk.name', 'value' => ['stringValue' => 'spp_otel_sdk']]
                    ]
                ],
                'scopeSpans' => [
                    [
                        'scope' => ['name' => 'spp.report.w3c_trace_context'],
                        'spans' => array_map(function ($s) {
                            $attrs = [];
                            foreach ($s['attributes'] as $k => $v) {
                                $attrs[] = ['key' => $k, 'value' => ['stringValue' => (string)$v]];
                            }
                            return [
                                'name' => $s['name'],
                                'traceId' => $s['trace_id'],
                                'spanId' => $s['span_id'],
                                'parentSpanId' => $s['parent_span_id'],
                                'attributes' => $attrs,
                                'startTimeUnixNano' => (int)$s['start_time_unix_nano'],
                                'endTimeUnixNano' => (int)$s['end_time_unix_nano'],
                            ];
                        }, $this->spans)
                    ]
                ]
            ]
        ];

        return ['resourceSpans' => $resourceSpans];
    }

    /**
     * Export the accumulated spans asynchronously without blocking the main request lifecycle.
     */
    public function export(): bool
    {
        if (empty($this->spans)) {
            return false;
        }

        $payload = json_encode($this->buildPayload());

        // Attempt asynchronous non-blocking dispatch via AsyncWorker if present
        if (class_exists('\SPPMod\SPPAsync\AsyncWorker') && method_exists('\SPPMod\SPPAsync\AsyncWorker', 'dispatchTask')) {
            \SPPMod\SPPAsync\AsyncWorker::dispatchTask('export_otel_spans', [
                'endpoint' => $this->endpoint,
                'payload' => $payload
            ]);
            $this->spans = [];
            return true;
        }

        // Fallback: Non-blocking cURL dispatch with ultra-short timeout
        $ch = curl_init($this->endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 200); // Non-blocking minimal timeout
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);

        @curl_exec($ch);
        @curl_close($ch);

        $this->spans = [];
        return true;
    }

    private static array $errors = [];

    public static function recordError(string $scope): void
    {
        if (!isset(self::$errors[$scope])) {
            self::$errors[$scope] = 0;
        }
        self::$errors[$scope]++;
    }

    public static function getErrorCount(string $scope): int
    {
        return self::$errors[$scope] ?? 0;
    }
}
