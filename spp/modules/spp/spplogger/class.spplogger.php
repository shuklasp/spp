<?php

namespace SPPMod\SPPLogger;

/*require_once 'class.sppdatabase.php';

require_once 'sppfuncs.php';
require_once 'class.sppsequence.php';
require_once 'class.sppbase.php';*/
/**
 * class Logger
 * Handles logging in the system.
 *
 * @author Satya Prakash Shukla
 */
class SPP_Logger extends \SPP\SPPObject
{
    /** PSR-3 Log Levels */
    public const EMERGENCY = 'emergency';
    public const ALERT = 'alert';
    public const CRITICAL = 'critical';
    public const ERROR = 'error';
    public const WARNING = 'warning';
    public const NOTICE = 'notice';
    public const INFO = 'info';
    public const DEBUG = 'debug';

    /**
     * Main delegator method for logging.
     *
     * @param string $message The log message
     * @param string $level   The log level (default: info)
     * @param array $context  Optional context data
     * @return void
     */
    public static function write_to_log($message, $level = self::INFO, array $context = [])
    {
        // Read configuration for the module
        $precedence = \SPP\Module::getConfig('log_precedence', 'spplogger') ?: 'db_first';
        $targets = (array) \SPP\Module::getConfig('log_targets', 'spplogger') ?: ['db', 'file'];

        // Extract metadata for the log entry
        $metadata = self::extractMetadata();
        $message = self::interpolate($message, $context);

        // [BEGIN CHANNEL SUPPORT]
        $channel = $context['channel'] ?? 'app';
        $metadata['channel'] = $channel;
        unset($context['channel']); // remove from context after extraction
        // [END CHANNEL SUPPORT]

        $dbSuccess = false;
        $fileSuccess = false;

        // Implementation of precedence logic
        if ($precedence === 'db_first') {
            $dbSuccess = self::write_to_db($message, $level, $metadata, $context);
            if (in_array('file', $targets)) {
                $fileSuccess = self::write_to_file($message, $level, $metadata, $context);
            }
        } elseif ($precedence === 'file_first') {
            $fileSuccess = self::write_to_file($message, $level, $metadata, $context);
            if (in_array('db', $targets)) {
                $dbSuccess = self::write_to_db($message, $level, $metadata, $context);
            }
        } else { // parallel or unrecognized
            if (in_array('db', $targets)) {
                $dbSuccess = self::write_to_db($message, $level, $metadata, $context);
            }
            if (in_array('file', $targets)) {
                $fileSuccess = self::write_to_file($message, $level, $metadata, $context);
            }
        }

        // Automatic fallback: if requested target failed, ensure the other is attempted
        if (!$dbSuccess && !$fileSuccess) {
            // If DB and File both enabled but failed, we are in trouble.
            // If only one was enabled and it failed, we try the other for resilience.
            if (in_array('db', $targets) && !in_array('file', $targets)) {
                self::write_to_file($message, $level, $metadata, $context);
            } elseif (in_array('file', $targets) && !in_array('db', $targets)) {
                self::write_to_db($message, $level, $metadata, $context);
            }
        }
    }

    private static $targetsMap = [];

    public static function setTarget(string $name, LoggerTargetInterface $target): void
    {
        self::$targetsMap[$name] = $target;
    }

    /**
     * Writes log entry to the database.
     */
    public static function write_to_db($message, $level, array $metadata, array $context = [])
    {
        if (isset(self::$targetsMap['db'])) {
            return self::$targetsMap['db']->write($message, $level, $metadata, $context);
        }

        if (class_exists('\SPPMod\SPPLogger\DBLoggerProvider')) {
            $provider = new \SPPMod\SPPLogger\DBLoggerProvider();
            self::setTarget('db', $provider);
            return $provider->write($message, $level, $metadata, $context);
        }

        return false;
    }

    /**
     * Writes log entry to a file with rotation and naming convention.
     */
    public static function write_to_file($message, $level, array $metadata, array $context = [])
    {
        try {
            $subdir = \SPP\Module::getConfig('log_subdir', 'spplogger') ?: '';
            $baseDir = defined('SPP_LOG_DIR') ? SPP_LOG_DIR : (defined('SPP_APP_DIR') ? SPP_APP_DIR . '/var/logs' : '/tmp');
            $targetDir = $baseDir . ($subdir ? '/' . $subdir : '');

            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0755, true);
            }

            $appname = \SPP\Scheduler::getContext() ?: 'default';
            $channel = $metadata['channel'] ?? 'app';
            $date = date('Y-m-d');
            $maxSize = (int) \SPP\Module::getConfig('max_file_size', 'spplogger') ?: 2097152;
            $format = \SPP\Module::getConfig('log_filename_format', 'spplogger') ?: 'log-{appname}-{channel}-{date}-{index}.txt';

            // Find current log number of the day
            $logNumber = 1;
            $currentFile = self::getFormattedFilename($format, $appname, $channel, $date, $level, $logNumber);

            while (file_exists($targetDir . "/" . $currentFile) && filesize($targetDir . "/" . $currentFile) >= $maxSize) {
                $logNumber++;
                $currentFile = self::getFormattedFilename($format, $appname, $date, $level, $logNumber);
            }

            $filePath = $targetDir . "/" . $currentFile;
            $cleanMessage = str_replace(["\r", "\n"], ' ', $message);
            $cleanUri = str_replace(["\r", "\n"], ' ', $metadata['uri']);
            $cleanUid = str_replace(["\r", "\n"], ' ', $metadata['uid']);
            $logLine = sprintf(
                "[%s] %s.%s: %s %s [URI: %s, UID: %s]\n",
                $metadata['timestamp'],
                strtoupper($appname),
                strtoupper($level),
                $cleanMessage,
                json_encode($context),
                $cleanUri,
                $cleanUid
            );

            file_put_contents($filePath, $logLine, FILE_APPEND);

            // Periodically check for retention
            if (mt_rand(1, 100) === 1) {
                self::runRetention($targetDir, $appname);
            }

            return true;
        } catch (\Exception $e) {
            error_log("Logging to File failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Formats the log filename based on the provided template and placeholders.
     */
    private static function getFormattedFilename($format, $appname, $channel, $date, $level, $index)
    {
        $replace = [
            '{appname}' => $appname,
            '{channel}' => $channel,
            '{date}' => $date,
            '{level}' => $level,
            '{index}' => $index
        ];

        $filename = strtr($format, $replace);

        // Failsafe: if {index} is missing but rotation is needed, append it
        if ($index > 1 && !str_contains($format, '{index}')) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $base = pathinfo($filename, PATHINFO_FILENAME);
            $filename = "{$base}-{$index}.{$ext}";
        }

        return $filename;
    }

    /**
     * Extracts common metadata for the log entry.
     */
    private static function extractMetadata()
    {
        $uid = '';
        $uname = '';
        if (\SPPMod\SPPAuth\SPPAuth::authSessionExists()) {
            $uid = \SPPMod\SPPAuth\SPPAuth::get('UserId');
            $uname = \SPPMod\SPPAuth\SPPAuth::get('UserName');
        }

        return [
            'uid' => $uid,
            'uname' => $uname,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'timestamp' => date('Y-m-d H:i:s'),
            'sessid' => session_id(),
            'uri' => $_SERVER['REQUEST_URI'] ?? 'cli',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'none'
        ];
    }

    /**
     * Placeholder interpolation for log messages.
     */
    private static function interpolate($message, array $context = [])
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }
        return strtr($message, $replace);
    }

    /**
     * Simple log retention logic.
     */
    private static function runRetention($dir, $appname)
    {
        $days = (int) \SPP\Module::getConfig('log_retention_days', 'spplogger');
        if ($days <= 0) {
            return; // Retain forever
        }

        $threshold = time() - ($days * 86400);

        foreach (glob($dir . "/log-{$appname}-*.txt") as $file) {
            if (filemtime($file) < $threshold) {
                unlink($file);
            }
        }
    }

    /** Backward compatibility helper */
    public static function log($message, $level = self::INFO, array $context = [])
    {
        self::write_to_log($message, $level, $context);
    }
    public static function error($message, array $context = [])
    {
        self::write_to_log($message, self::ERROR, $context);
    }
    public static function debug($message, array $context = [])
    {
        self::write_to_log($message, self::DEBUG, $context);
    }
    public static function info($message, array $context = [])
    {
        self::write_to_log($message, self::INFO, $context);
    }
}
