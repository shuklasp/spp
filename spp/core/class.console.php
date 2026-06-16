<?php

namespace SPP\CLI;

/**
 * Class Console
 * Provides utility methods for CLI interactions.
 */
class Console
{
    /**
     * Function to read interactive input
     *
     * @param string $text The prompt text
     * @param string $default The default value if input is empty
     * @return string
     */
    public static function prompt(string $text, string $default = ''): string
    {
        $extra = ($default !== '') ? " [{$default}]" : "";
        echo $text . $extra . ": ";
        $input = trim(fgets(STDIN));
        return ($input === '') ? $default : $input;
    }

    /**
     * Basic Table Formatter for CLI
     *
     * @param array $headers The table headers
     * @param array $rows The table rows (associative arrays)
     */
    public static function printTable(array $headers, array $rows): void
    {
        if (empty($rows)) {
            echo "(Empty set)\n";
            return;
        }
        $widths = array();
        foreach ($headers as $i => $h) {
            $widths[$i] = strlen($h);
        }
        foreach ($rows as $row) {
            $rValues = array_values($row);
            foreach ($rValues as $i => $v) {
                $widths[$i] = max($widths[$i] ?? 0, strlen((string)$v));
            }
        }

        $line = "+";
        foreach ($widths as $w) {
            $line .= str_repeat("-", $w + 2) . "+";
        }
        echo $line . "\n";

        echo "|";
        foreach ($headers as $i => $h) {
            echo " " . str_pad($h, $widths[$i]) . " |";
        }
        echo "\n" . $line . "\n";

        foreach ($rows as $row) {
            echo "|";
            $rValues = array_values($row);
            foreach ($rValues as $i => $v) {
                echo " " . str_pad((string)substr($v, 0, 50), $widths[$i]) . " |";
            }
            echo "\n";
        }
        echo $line . "\n";
    }
}
