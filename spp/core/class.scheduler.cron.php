<?php

namespace SPP\Cron;

/**
 * Class Scheduler
 * Manages scheduled tasks (Cron) in the SPP framework.
 */
class Scheduler
{
    protected static array $tasks = [];

    /**
     * Schedule a Closure or Command to run at a specific interval.
     */
    public static function call(\Closure $task, string $cronExpression = '* * * * *'): void
    {
        self::$tasks[] = [
            'task' => $task,
            'expression' => $cronExpression
        ];
    }

    /**
     * Evaluates a standard 5-part cron expression against a timestamp.
     */
    public static function matchCron(string $expr, int $time = null): bool
    {
        $time = $time ?: time();
        $parts = preg_split('/\s+/', trim($expr));
        if (count($parts) !== 5) return false;

        $minute = (int) date('i', $time);
        $hour   = (int) date('G', $time);
        $day    = (int) date('j', $time);
        $month  = (int) date('n', $time);
        $dow    = (int) date('w', $time);

        return self::matchCronField($parts[0], $minute)
            && self::matchCronField($parts[1], $hour)
            && self::matchCronField($parts[2], $day)
            && self::matchCronField($parts[3], $month)
            && self::matchCronField($parts[4], $dow);
    }

    private static function matchCronField(string $field, int $value): bool
    {
        if ($field === '*') return true;
        
        $list = explode(',', $field);
        foreach ($list as $item) {
            if (str_contains($item, '/')) {
                $parts = explode('/', $item);
                $range = $parts[0];
                $step = (int) ($parts[1] ?? 1);
                if ($range === '*') {
                    if ($value % $step === 0) return true;
                } else {
                    $rParts = explode('-', $range);
                    $start = (int) $rParts[0];
                    $end = (int) ($rParts[1] ?? $rParts[0]);
                    if ($value >= $start && $value <= $end && ($value - $start) % $step === 0) return true;
                }
            } elseif (str_contains($item, '-')) {
                $rParts = explode('-', $item);
                $start = (int) $rParts[0];
                $end = (int) ($rParts[1] ?? $rParts[0]);
                if ($value >= $start && $value <= $end) return true;
            } else {
                if ($value === (int)$item) return true;
            }
        }
        return false;
    }

    /**
     * Run the scheduled tasks that match the current minute.
     * Triggered via: php spp.php schedule:run
     */
    public static function run(): void
    {
        $logEvent = function($level, $message) {
            if (class_exists('\\SPP\\SPPEvent')) {
                \SPP\SPPEvent::fireEvent('log', new \SPP\EventParams([
                    'level' => $level,
                    'message' => $message
                ]));
            } else {
                echo "[" . date('Y-m-d H:i:s') . "] [$level] " . $message . "\n";
            }
        };

        $msg = "Running scheduled tasks...";
        $logEvent('info', $msg);
        
        $now = time();
        $ran = 0;
        foreach (self::$tasks as $t) {
            if (self::matchCron($t['expression'], $now)) {
                try {
                    $t['task']();
                    $ran++;
                } catch (\Exception $e) {
                    $errMsg = "Task failed: " . $e->getMessage();
                    $logEvent('error', $errMsg);
                }
            }
        }
        
        $msgDone = "Executed {$ran} tasks.";
        $logEvent('info', $msgDone);
    }
}
