<?php

/**
 * SPPXDB Interactive Shell
 *
 * A powerful CLI for communicating with the XML Database.
 * Usage: php xdb-shell.php
 */

require_once __DIR__ . '/class.sppxdb.php';

use SPPMod\SPPXDB\SPP_XDB;

class XDBShell
{
    private $xdb;
    private $currentDb = 'default';

    public function run()
    {
        $this->printHeader();
        $this->xdb = new SPP_XDB($this->currentDb);

        while (true) {
            if (function_exists('readline')) {
                $line = readline("xdb({$this->currentDb})> ");
                if ($line !== false) {
                    readline_add_history($line);
                }
            } else {
                echo "xdb({$this->currentDb})> ";
                if (function_exists('fflush')) {
                    fflush(STDOUT);
                }
                $line = fgets(STDIN);
            }

            if ($line === false) {
                break;
            }

            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            if ($this->handleShellCommand($line)) {
                continue;
            }

            try {
                $results = $this->xdb->querySQL($line);
                if (is_array($results)) {
                    $this->printResults($results);
                } else {
                    echo "Result: " . json_encode($results, JSON_PRETTY_PRINT) . "\n";
                }
            } catch (Exception $e) {
                echo "\033[31mError: " . $e->getMessage() . "\033[0m\n";
            }
        }
    }

    private function handleShellCommand($line)
    {
        $cmd = strtolower($line);
        if ($cmd === 'exit' || $cmd === 'quit') {
            exit(0);
        }
        if ($cmd === 'clear' || $cmd === 'cls') {
            echo "\033[2J\033[H";
            return true;
        }
        if ($cmd === 'help') {
            $this->printHelp();
            return true;
        }

        if (preg_match('/^use\s+([a-zA-Z0-9_]+)/i', $line, $m)) {
            $this->currentDb = $m[1];
            $this->xdb = new SPP_XDB($this->currentDb);
            echo "Database changed to: {$this->currentDb}\n";
            return true;
        }

        return false;
    }

    private function printResults($data)
    {
        if (empty($data)) {
            echo "Empty result set.\n";
            return;
        }

        if (!isset($data[0]) || !is_array($data[0])) {
            echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
            return;
        }

        $keys = array_keys($data[0]);
        $widths = [];
        foreach ($keys as $key) {
            $widths[$key] = strlen($key);
        }

        foreach ($data as $row) {
            foreach ($keys as $key) {
                $val = is_scalar($row[$key]) ? (string)$row[$key] : '[obj]';
                $widths[$key] = max($widths[$key], strlen($val));
            }
        }

        // Header
        $this->printRow($keys, $widths);
        $this->printSeparator($widths);

        // Body
        foreach ($data as $row) {
            $this->printRow($row, $widths);
        }

        echo "(" . count($data) . " rows in set)\n\n";
    }

    private function printRow($row, $widths)
    {
        echo "|";
        foreach ($widths as $key => $width) {
            $val = isset($row[$key]) ? (is_scalar($row[$key]) ? $row[$key] : '[obj]') : '';
            echo " " . str_pad($val, $width) . " |";
        }
        echo "\n";
    }

    private function printSeparator($widths)
    {
        echo "+";
        foreach ($widths as $width) {
            echo str_repeat("-", $width + 2) . "+";
        }
        echo "\n";
    }

    private function printHeader()
    {
        echo "\033[36m";
        echo "========================================\n";
        echo "   SPPXDB INTERACTIVE SHELL V2.0        \n";
        echo "========================================\n";
        echo "\033[0m";
        echo "Type 'help' for commands, 'exit' to quit.\n\n";
    }

    private function printHelp()
    {
        echo "\nShell Commands:\n";
        echo "  USE [db]         Switch database context\n";
        echo "  HELP             Show this help\n";
        echo "  CLEAR            Clear screen\n";
        echo "  EXIT / QUIT      Exit shell\n\n";
        echo "SQL/XPath Support:\n";
        echo "  SHOW DATABASES;  List all DBs\n";
        echo "  SHOW TABLES;     List tables in current DB\n";
        echo "  DESC [table];    Describe table schema\n";
        echo "  SELECT ...       Standard query support\n\n";
    }
}

$shell = new XDBShell();
$shell->run();
