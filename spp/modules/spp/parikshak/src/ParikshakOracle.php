<?php
namespace SPPMod\Parikshak;

use Symfony\Component\Yaml\Yaml;

/**
 * Class ParikshakOracle
 * Predictive Analysis, Migration Logging, and Autonomous Upgrades.
 */
class ParikshakOracle
{
    /**
     * Elite Upgrade Engine: Mass-refactors existing entities to enterprise standards.
     */
    public function bulkUpgradeAll(string $appname): array
    {
        $entities = $this->getEntitiesForApp($appname);
        $results = ['total' => count($entities), 'upgraded' => 0, 'errors' => []];

        foreach ($entities as $class) {
            try {
                if ($this->upgradeEntityToElite($class)) {
                    $results['upgraded']++;
                }
            } catch (\Exception $e) {
                $results['errors'][] = $class . ": " . $e->getMessage();
            }
        }
        return $results;
    }

    public function upgradeEntityToElite(string $entityClass): bool
    {
        $refl = new \ReflectionClass($entityClass);
        $appname = strtolower(explode('\\', $entityClass)[1]);
        $entityName = $refl->getShortName();
        $yamlPath = SPP_APP_DIR . "/etc/apps/{$appname}/entities/" . strtolower($entityName) . ".yml";

        if (!file_exists($yamlPath)) {
            return false;
        }

        $content = file_get_contents($yamlPath);
        $data = Yaml::parse($content);

        // 1. Force Auditing
        $data['audit'] = true;

        // 2. Harden Columns
        if (isset($data['attributes']) && is_array($data['attributes'])) {
            foreach ($data['attributes'] as $col => $type) {
                if (preg_match("/varchar\((\d+)\)/i", $type, $m)) {
                    $len = (int)$m[1];
                    if ($len < 255) {
                        $data['attributes'][$col] = 'varchar(255)';
                    }
                }
            }
            
            // 3. Inject Timestamps if missing
            if (!isset($data['attributes']['created_at'])) {
                $data['attributes']['created_at'] = 'varchar(50)';
            }
            if (!isset($data['attributes']['updated_at'])) {
                $data['attributes']['updated_at'] = 'varchar(50)';
            }
        }

        file_put_contents($yamlPath, Yaml::dump($data, 4, 2));
        $this->logMigration($entityClass, "Upgraded to Elite Standards (Auditing, Hardened Columns, Timestamps)");
        return true;
    }

    private function getEntitiesForApp(string $appname): array
    {
        $appEntitiesDir = SPP_APP_DIR . '/src/' . $appname . '/entities';
        $files = glob($appEntitiesDir . '/entity.*.php');
        $entities = [];
        foreach ($files as $file) {
            $entityName = $this->resolveEntityClass($file, $appname);
            if ($entityName) {
                $entities[] = $entityName;
            }
        }
        return $entities;
    }

    private function resolveEntityClass(string $file, string $appname): ?string
    {
        $baseName = basename($file, '.php');
        if (str_starts_with($baseName, 'entity.')) {
            $entityName = substr($baseName, 7);
        } else {
            $entityName = $baseName;
        }

        $className = "App\\" . ucfirst($appname) . "\\Entities\\" . ucfirst($entityName);
        if (class_exists($className)) {
            return $className;
        }

        try {
            require_once $file;
            if (class_exists($className)) {
                return $className;
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    /**
     * Predictive Failure Analysis (The Oracle)
     */
    public function runOracleAnalysis(): array
    {
        $historyPath = SPP_APP_DIR . "/var/reports/parikshak_history.json";
        if (!file_exists($historyPath)) {
            return ['message' => 'Insufficient data for analysis.'];
        }

        $history = json_decode(file_get_contents($historyPath), true);
        $totalFailures = 0;

        foreach ($history as $run) {
            $totalFailures += $run['summary']['failed'] ?? 0;
        }

        return [
            'risk_level' => $totalFailures > 10 ? 'High' : 'Low',
            'insight' => "Based on " . count($history) . " runs, your system has a " . round(($totalFailures / count($history)), 1) . " avg failure rate per scan.",
            'recommendation' => $totalFailures > 0 ? "Focus on hardening Entity boundaries and Audit compliance." : "Architecture is stable."
        ];
    }

    /**
     * Migration Tracking: Logs schema changes for version control.
     */
    public function logMigration(string $entityClass, string $change): void
    {
        $dir = SPP_APP_DIR . "/var/migrations";
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $version = date('YmdHis');
        $file = $dir . "/migration_{$version}.sql";
        file_put_contents($file, "-- Migration for {$entityClass}\n-- Change: {$change}\n");
    }

    /**
     * Autonomous Self-Correction: Updates the YAML manifest with suggested fixes.
     */
    public function applyFix(string $entityClass, array $fix): bool
    {
        try {
            $refl = new \ReflectionClass($entityClass);
            $appname = strtolower(explode('\\', $entityClass)[1]);
            $entityName = $refl->getShortName();
            $yamlPath = SPP_APP_DIR . "/etc/apps/{$appname}/entities/" . strtolower($entityName) . ".yml";

            if (!file_exists($yamlPath)) {
                return false;
            }

            // Simple heuristic fix: if it's a length fix, we update the type
            if ($fix['type'] === 'Schema Fix' && preg_match("/column '([^']+)'/i", $fix['message'], $m)) {
                $col = $m[1];
                $content = file_get_contents($yamlPath);
                $data = Yaml::parse($content);

                if (isset($data['attributes'][$col])) {
                    $type = strtolower($data['attributes'][$col]);
                    if (strpos($type, 'varchar') !== false) {
                        $data['attributes'][$col] = 'text'; // Upgrade to text for safety
                        file_put_contents($yamlPath, Yaml::dump($data, 4, 2));
                        $this->logMigration($entityClass, "Upgraded column '{$col}' to text due to overflow.");
                        return true;
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Failed to apply fix: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Deterministic Expert System: Heuristic Error Diagnosis
     */
    public function diagnoseError(string $error, string $entityClass): array
    {
        $suggestions = [];

        // Pattern 1: String Truncation
        if (preg_match("/String data, right truncated/i", $error) || preg_match("/Data too long for column '([^']+)'/i", $error, $m)) {
            $col = $m[1] ?? "unknown_column";
            $suggestions[] = [
                'type' => 'Schema Fix',
                'message' => "The value provided exceeds the column width of '{$col}'.",
                'action' => "Increase the length of '{$col}' in your YAML manifest or database."
            ];
        }

        // Pattern 2: Integrity Constraint
        if (preg_match("/Integrity constraint violation/i", $error)) {
            $suggestions[] = [
                'type' => 'Architectural Fix',
                'message' => "A required relationship or unique constraint was violated.",
                'action' => "Check if all mandatory foreign keys (ManyToOne) are correctly mapped in your metadata."
            ];
        }

        // Pattern 3: Unicode / Collation
        if (preg_match("/Incorrect string value/i", $error) || preg_match("/UTF-8 Corrupted/i", $error)) {
            $suggestions[] = [
                'type' => 'Database Fix',
                'message' => "Non-standard characters (Unicode) caused a storage failure.",
                'action' => "Ensure your database table collation is set to 'utf8mb4_unicode_ci'."
            ];
        }

        return $suggestions;
    }
}
