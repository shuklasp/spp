<?php

namespace SPPMod\SPPAI;

use SPP\SPPObject;
use SPP\Module;
use SPP\Core\SchemaValidator;

/**
 * AiMigrationAdvisor
 * Observes database exception logs for schema anomalies (e.g., missing columns, table mismatches)
 * and uses SPPAI (Ollama by default) to automatically propose self-healing database migrations.
 */
class AiMigrationAdvisor extends SPPObject
{
    /**
     * Inspects a database exception or error log message and generates a proposed migration file.
     *
     * @param string $exceptionMessage The raw database error/exception message
     * @param string $appName The application context name
     * @return string|null Path to the generated migration file, or null on failure
     */
    public static function adviseAndGenerateMigration(string $exceptionMessage, string $appName = 'default'): ?string
    {
        return \SPP\Scheduler::withContext($appName, function() use ($exceptionMessage, $appName) {
            Module::loadModule('sppai');
            Module::loadModule('sppmaker');

            // Configurable in app config, ollama by default
            $provider = \SPP\App::getConfig('ai_advisor_provider', $appName) ?: Module::getConfig('advisor_provider', 'sppai') ?: 'ollama';

            $prompt = <<<PROMPT
You are an expert AI database migration advisor for the SPP PHP Framework.
An application encountered the following database exception/error:
"{$exceptionMessage}"

Analyze the error and determine if it represents a missing table, missing column, or index mismatch.
If a migration is needed to fix this, respond with ONLY a JSON object having the following structure:
{
    "migration_name": "add_missing_columns_to_table",
    "table_name": "target_table_name",
    "action": "alter",
    "columns": [
        {"name": "column_name", "type": "VARCHAR(255)", "nullable": true}
    ]
}
Do not include markdown formatting, explanations, or any text outside the JSON object.
PROMPT;

            try {
                $response = SPPAI::using($provider)::complete($prompt);
                $cleanJson = trim(preg_replace('/^```json|```$/i', '', $response));
                $data = json_decode($cleanJson, true);

                if (empty($data['migration_name']) || empty($data['table_name'])) {
                    return null;
                }

                if (!class_exists('\SPP\Core\SchemaValidator')) {
                    require_once SPP_APP_DIR . '/spp/core/class.schemavalidator.php';
                }

                $migrationName = SchemaValidator::escapeIdentifier($data['migration_name']);
                $tableName = SchemaValidator::escapeIdentifier($data['table_name']);

                // Generate migration content
                $timestamp = date('YmdHis');
                $className = 'Migration_' . $timestamp . '_' . $migrationName;

                $upQueries = "";
                $downQueries = "";

                if (($data['action'] ?? '') === 'alter' && !empty($data['columns'])) {
                    foreach ($data['columns'] as $col) {
                        $colName = SchemaValidator::escapeIdentifier($col['name']);
                        $colType = $col['type'] ?? 'VARCHAR(255)';
                        $upQueries .= "        \$this->execute(\"ALTER TABLE `{$tableName}` ADD COLUMN `{$colName}` {$colType}\");\n";
                        $downQueries .= "        \$this->execute(\"ALTER TABLE `{$tableName}` DROP COLUMN `{$colName}`\");\n";
                    }
                } else {
                    $upQueries .= "        // Add table creation logic for {$tableName}\n";
                    $downQueries .= "        \$this->execute(\"DROP TABLE IF EXISTS `{$tableName}`\");\n";
                }

                $migrationContent = <<<MIGRATION
<?php

namespace App\\{$appName}\\Migrations;

use SPP\Core\Database\Migration;

/**
 * AI-Generated Self-Healing Migration: {$migrationName}
 * Automated remediation for observed database exception.
 */
class {$className} extends Migration
{
    public function up(): void
    {
{$upQueries}    }

    public function down(): void
    {
{$downQueries}    }
}

MIGRATION;

                $targetDir = SPP_APP_DIR . ($appName === 'default' ? '/spp/migrations' : "/src/{$appName}/migrations");
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }

                $filePath = $targetDir . '/' . $timestamp . '_' . $migrationName . '.php';
                file_put_contents($filePath, $migrationContent);

                return $filePath;
            } catch (\Exception $e) {
                error_log("AiMigrationAdvisor Error: " . $e->getMessage());
                return null;
            }
        });
    }
}
