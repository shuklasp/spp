<?php
namespace SPPMod\SPPReport\Services;

class AiReportService
{
    public function analyze(\SPPReport $report, array $config): string
    {
        if (!class_exists('\\SPPMod\\SPPAI\\SPPAI')) {
            throw new \Exception("SPPAI module is required for automated insights.");
        }

        $config['limit'] = 100;
        $result = $report->runReport($config);

        if (empty($result['data'])) {
            throw new \Exception("No data to analyze.");
        }

        $prompt = "You are an expert Data Analyst. Analyze the following report data and provide a concise, 3-bullet-point executive summary of the key insights, trends, or anomalies.\n\nData (first 100 rows max):\n";
        $prompt .= json_encode($result['data']);

        return \SPPMod\SPPAI\SPPAI::generate($prompt);
    }

    public function build(\SPPReport $report, string $query): array
    {
        if (!class_exists('\\SPPMod\\SPPAI\\SPPAI')) {
            throw new \Exception("SPPAI module is not installed or enabled. Cannot generate AI reports.");
        }

        if (empty($query)) {
            throw new \Exception("Natural language query is required.");
        }

        $schema = $report->getSchema();
        $schemaJson = json_encode($schema);

        $prompt = "You are an AI that converts natural language to a JSON report configuration for SPPReport BI.\n";
        $prompt .= "Database Schema: $schemaJson\n";
        $prompt .= "User Request: $query\n";
        $prompt .= "You must generate a strictly valid JSON configuration with the following structure. Do NOT include markdown blocks.\n";

        $jsonSchema = [
            'type' => 'object',
            'properties' => [
                'table' => ['type' => 'string', 'description' => 'The base table name'],
                'columns' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'field' => ['type' => 'string'],
                            'aggregate' => ['type' => 'string', 'enum' => ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'CUSTOM', '']],
                            'alias' => ['type' => 'string']
                        ]
                    ]
                ],
                'joins' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'table' => ['type' => 'string'],
                            'type' => ['type' => 'string', 'enum' => ['LEFT JOIN', 'INNER JOIN']],
                            'on' => ['type' => 'string']
                        ]
                    ]
                ]
            ],
            'required' => ['table', 'columns']
        ];

        $response = \SPPMod\SPPAI\SPPAI::structured($prompt, $jsonSchema);
        $configObj = is_string($response) ? json_decode($response, true) : $response;
        if (!$configObj) {
            throw new \Exception("AI failed to generate a valid report configuration.");
        }

        return $configObj;
    }

    public function evaluateAnomaly(string $condition, array $data): bool
    {
        if (!class_exists('\\SPPMod\\SPPAI\\SPPAI')) {
            // Degrade gracefully: if no AI, always trigger the alert
            return true;
        }

        $prompt = "You are an AI Anomaly Detection system. You will evaluate a dataset against a specific alerting condition.\n";
        $prompt .= "If the condition is met, respond with exactly and only the string 'YES'. If not, respond with 'NO'.\n\n";
        $prompt .= "Alert Condition: \"$condition\"\n\n";
        $prompt .= "Data Snapshot:\n" . json_encode($data);

        $response = \SPPMod\SPPAI\SPPAI::generate($prompt);
        return strtoupper(trim($response)) === 'YES';
    }
}
