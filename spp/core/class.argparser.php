<?php

namespace SPP\CLI;

/**
 * Class ArgParser
 * A robust utility for parsing raw CLI arguments into options, flags, and positional arguments.
 */
class ArgParser
{
    /**
     * Parses raw $argv into structured options and arguments.
     *
     * @param array $argv The raw $argv array
     * @return array Returns an array with 'options' and 'arguments' keys.
     */
    public static function parse(array $argv): array
    {
        $result = [
            'options' => [],
            'arguments' => [],
        ];

        // Skip the first argument if it looks like the script name (ends with .php)
        // or if it's the command name itself (we usually just parse the whole array
        // but it's safe to keep the raw ones and just categorize them)
        
        $isFirst = true;
        
        for ($i = 0; $i < count($argv); $i++) {
            $arg = $argv[$i];

            // If it's the script name, just ignore it
            if ($isFirst && (str_ends_with($arg, '.php') || $arg === 'spp')) {
                $isFirst = false;
                continue;
            }
            $isFirst = false;

            // Long options: --key=value or --flag
            if (str_starts_with($arg, '--')) {
                $eqPos = strpos($arg, '=');
                if ($eqPos !== false) {
                    $key = substr($arg, 2, $eqPos - 2);
                    $value = substr($arg, $eqPos + 1);
                    $result['options'][$key] = $value;
                } else {
                    $key = substr($arg, 2);
                    $result['options'][$key] = true; // Strictly boolean if no = is provided
                }
            } 
            // Short options: -k value or -k=value
            elseif (str_starts_with($arg, '-') && strlen($arg) > 1) {
                $eqPos = strpos($arg, '=');
                if ($eqPos !== false) {
                    $key = substr($arg, 1, $eqPos - 1);
                    $value = substr($arg, $eqPos + 1);
                    $result['options'][$key] = $value;
                } else {
                    $key = substr($arg, 1);
                    if (isset($argv[$i + 1]) && !str_starts_with($argv[$i + 1], '-')) {
                        $result['options'][$key] = $argv[$i + 1];
                        $i++;
                    } else {
                        $result['options'][$key] = true;
                    }
                }
            } 
            // Positional arguments
            else {
                $result['arguments'][] = $arg;
            }
        }

        return $result;
    }
}
