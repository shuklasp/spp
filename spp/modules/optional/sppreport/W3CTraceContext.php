<?php

namespace SPPMod\SPPReport;

if (!class_exists('\\SPPMod\\SPPReport\\Services\\OpenTelemetryExporter')) {
    require_once __DIR__ . '/services/OpenTelemetryExporter.php';
}

/**
 * W3CTraceContext
 * Distributed tracing propagation engine with asynchronous OpenTelemetry exporting.
 */
class W3CTraceContext
{
    private static string $traceparent = '';
    private static string $tracestate = '';
    private static ?\SPPMod\SPPReport\Services\OpenTelemetryExporter $exporter = null;

    public static function inject(array &$headers): void
    {
        if (empty(self::$traceparent)) {
            $traceId = bin2hex(random_bytes(16));
            $spanId = bin2hex(random_bytes(8));
            self::$traceparent = "00-{$traceId}-{$spanId}-01";
        }
        $headers['traceparent'] = self::$traceparent;
        if (!empty(self::$tracestate)) {
            $headers['tracestate'] = self::$tracestate;
        }
    }

    public static function extract(array $headers): void
    {
        if (isset($headers['traceparent'])) {
            self::$traceparent = $headers['traceparent'];
        }
        if (isset($headers['tracestate'])) {
            self::$tracestate = $headers['tracestate'];
        }
    }

    public static function startSpan(string $name, array $attributes = []): void
    {
        if (empty(self::$traceparent)) {
            $traceId = bin2hex(random_bytes(16));
            $spanId = bin2hex(random_bytes(8));
            self::$traceparent = "00-{$traceId}-{$spanId}-01";
        }

        $parts = explode('-', self::$traceparent);
        $traceId = $parts[1] ?? bin2hex(random_bytes(16));
        $parentSpanId = $parts[2] ?? '';
        $newSpanId = bin2hex(random_bytes(8));

        // Update active traceparent to new span ID
        self::$traceparent = "00-{$traceId}-{$newSpanId}-01";

        if (self::$exporter === null) {
            self::$exporter = new \SPPMod\SPPReport\Services\OpenTelemetryExporter();
        }

        self::$exporter->addSpan($name, $traceId, $newSpanId, $parentSpanId, $attributes);
        self::$exporter->export();
    }
}
