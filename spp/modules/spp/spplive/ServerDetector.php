<?php
namespace SPPMod\SPPLive;

class StreamingDegradationException extends \RuntimeException {}

/**
 * Detects the web server environment to apply best settings for SSE/long-polling streaming.
 */
class ServerDetector {
    private static $cacheFile = SPP_BASE_DIR . '/var/cache/server_streaming_config.json';

    public static function applyStreamingHeaders(): void {
        $forcePolling = \SPP\App::getGlobalSettings('security.streaming.force_polling') ?? false;
        
        $settings = self::getSettings();
        
        if ($forcePolling || ($settings['is_apache'] && !function_exists('set_time_limit'))) {
            throw new StreamingDegradationException("Streaming is degraded to short-polling by configuration or restricted environment.");
        }

        // Standard SSE Headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Connection: keep-alive');
        
        // Server-specific headers to disable buffering/compression
        if ($settings['is_apache']) {
            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', '1');
            }
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');
        }

        if ($settings['is_nginx']) {
            header('X-Accel-Buffering: no');
        }

        if ($settings['is_litespeed']) {
            header('X-LiteSpeed-Cache-Control: no-cache');
        }

        // Disable PHP time limit for long-running stream
        set_time_limit(0);
        
        // Attempt to turn off output buffering
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        ob_implicit_flush(1);
    }

    private static function getSettings(): array {
        if (file_exists(self::$cacheFile)) {
            $cached = json_decode(file_get_contents(self::$cacheFile), true);
            if (is_array($cached) && isset($cached['detected_at']) && time() - $cached['detected_at'] < 86400) {
                return $cached;
            }
        }

        $serverSoft = $_SERVER['SERVER_SOFTWARE'] ?? '';
        
        $settings = [
            'is_apache'    => stripos($serverSoft, 'apache') !== false,
            'is_nginx'     => stripos($serverSoft, 'nginx') !== false,
            'is_litespeed' => stripos($serverSoft, 'litespeed') !== false,
            'detected_at'  => time()
        ];

        if (!is_dir(dirname(self::$cacheFile))) {
            @mkdir(dirname(self::$cacheFile), 0777, true);
        }
        
        @file_put_contents(self::$cacheFile, json_encode($settings));

        return $settings;
    }
}
