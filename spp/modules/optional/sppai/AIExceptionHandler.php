<?php

namespace SPPMod\SPPAI;

/**
 * AIExceptionHandler
 * Self-Healing AI Exception Analyzer that integrates with SPPAI to automatically inspect
 * stack traces and offer plain-English root cause explanations and copy-pasteable solutions.
 */
class AIExceptionHandler
{
    /**
     * Analyze an exception or throwable using configured AI models.
     *
     * @param \Throwable $e The caught exception or error.
     * @return array Containing 'root_cause', 'recommended_fix', and 'diff'.
     */
    public static function analyze(\Throwable $e): array
    {
        if (!class_exists('\SPPMod\SPPAI\SPPAI')) {
            require_once __DIR__ . '/class.sppai.php';
        }

        $message = $e->getMessage();
        $file = $e->getFile();
        $line = $e->getLine();
        $trace = $e->getTraceAsString();

        // Safely extract surrounding lines of code
        $codeSnippet = "";
        if (file_exists($file) && is_readable($file)) {
            $lines = @file($file);
            if (is_array($lines)) {
                $start = max(0, $line - 10);
                $slice = array_slice($lines, $start, 20, true);
                foreach ($slice as $lineNum => $lineContent) {
                    $actualLine = $lineNum + 1;
                    $prefix = ($actualLine === $line) ? ">> " : "   ";
                    $codeSnippet .= "{$prefix}{$actualLine}: " . rtrim($lineContent) . "\n";
                }
            }
        }

        $prompt = "Analyze the following PHP exception and provide a root cause analysis and a recommended fix with a diff block:\n\n"
            . "Exception: {$message}\n"
            . "File: {$file} on line {$line}\n\n"
            . "Code Snippet:\n{$codeSnippet}\n\n"
            . "Stack Trace:\n{$trace}";

        $tools = [
            [
                'name' => 'provide_exception_solution',
                'description' => 'Provide a structured solution for an uncaught exception or fatal error.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'root_cause' => ['type' => 'string', 'description' => 'Plain English explanation of why the error occurred for a novice developer'],
                        'recommended_fix' => ['type' => 'string', 'description' => 'Explanation of the fix'],
                        'diff' => ['type' => 'string', 'description' => 'Copy-pasteable diff block to resolve the issue']
                    ],
                    'required' => ['root_cause', 'recommended_fix', 'diff']
                ]
            ]
        ];

        try {
            $result = SPPAI::using('ollama')::callTool($prompt, $tools);
            if (is_array($result) && isset($result['root_cause'])) {
                return $result;
            }
            if (is_string($result)) {
                return [
                    'root_cause' => $result,
                    'recommended_fix' => 'Apply the recommendations outlined in the analysis above.',
                    'diff' => " // No specific diff generated\n"
                ];
            }
        } catch (\Exception $aiException) {
            // Fallback if AI provider is unreachable during local exception handling
            return [
                'root_cause' => "AI Provider unreachable (" . $aiException->getMessage() . "). Base Error: {$message}",
                'recommended_fix' => "Check line {$line} in {$file} and verify local AI service connectivity.",
                'diff' => " // Verify ollama daemon is running locally\n"
            ];
        }

        return [
            'root_cause' => "Unable to determine root cause via AI. Exception: {$message}",
            'recommended_fix' => "Manually inspect {$file} at line {$line}.",
            'diff' => ""
        ];
    }
}
