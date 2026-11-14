<?php
declare(strict_types=1);

namespace SPPMod\SPPDB\Seeding;

use SPPMod\SPPDB\SmartDataGenerator;
use SPPMod\SPPDB\SPPDB;
use PDO;
use Throwable;

/**
 * Class SPPSeeder
 *
 * First-class, fluent database and domain entity seeding engine for the SPP Framework.
 * Synthesizes realistic, context-aware mock data directly from declarative entity schemas
 * and workflow lifecycles, supporting fluent truncation, workflow state targeting,
 * and multi-seeder execution.
 *
 * @package SPPMod\SPPDB\Seeding
 * @author Satya Prakash Shukla
 */
abstract class SPPSeeder
{
    /**
     * @var array<string> Internal log messages from the seeding execution.
     */
    protected array $logs = [];

    /**
     * Run the database seeds.
     */
    abstract public function run(): void;

    /**
     * Call another seeder class or an array of seeder classes.
     *
     * @param string|array<string> $classes
     */
    protected function call(string|array $classes): void
    {
        $classList = is_array($classes) ? $classes : [$classes];
        foreach ($classList as $class) {
            $start = microtime(true);
            $this->log("Calling seeder: {$class}...");
            $seeder = new $class();
            $seeder->run();
            $duration = round((microtime(true) - $start) * 1000, 2);
            $this->log("Completed seeder: {$class} in {$duration} ms.");
        }
    }

    /**
     * Seeds N realistic records for the specified entity class or short name.
     * Derived automatically from declarative schemas, validations, and relations.
     *
     * @param string $entityClassOrName Class name (e.g. App\School\Entities\Student) or short name
     * @param int $count Number of records to generate (default 10)
     * @param array $overrides Attribute overrides applied to every generated record
     * @param PDO|null $pdo Optional PDO instance for isolated testing
     * @param string|null $appName Optional application context
     * @return array Array of created records
     */
    protected function seed(string $entityClassOrName, int $count = 10, array $overrides = [], ?PDO $pdo = null, ?string $appName = null): array
    {
        $start = microtime(true);
        $records = SmartDataGenerator::seed($entityClassOrName, $count, $overrides, $pdo, $appName);
        $duration = round((microtime(true) - $start) * 1000, 2);
        $this->log("Seeded {$count} record(s) for '{$entityClassOrName}' in {$duration} ms.");
        return $records;
    }

    /**
     * Seeds N realistic records in a specific workflow lifecycle state or
     * uniformly distributed across valid workflow states.
     *
     * @param string $entityClassOrName Class name or short name
     * @param int $count Number of records to generate
     * @param string|null $state Target workflow state (e.g. 'draft', 'submitted', 'approved', or null for random distribution)
     * @param array $overrides Additional attribute overrides
     * @return array Array of created records
     */
    protected function seedWorkflow(string $entityClassOrName, int $count = 10, ?string $state = null, array $overrides = []): array
    {
        if ($state !== null && $state !== '') {
            $overrides['status'] = $state;
            $overrides['workflow_state'] = $state;
        }
        $records = $this->seed($entityClassOrName, $count, $overrides);
        $targetDesc = $state ? "in state '{$state}'" : "with distributed workflow states";
        $this->log("Workflow seeding: {$count} record(s) for '{$entityClassOrName}' {$targetDesc}.");
        return $records;
    }

    /**
     * Safely truncates one or more database tables with cross-driver constraint handling.
     * Supports SQLite, MySQL, and PostgreSQL.
     *
     * @param string ...$tables Table names to truncate
     */
    protected function truncate(string ...$tables): void
    {
        try {
            $db = SPPDB::getInstance();
            $pdo = $db->getPDO();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

            foreach ($tables as $table) {
                // Sanitize identifier
                $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
                if (empty($table)) continue;

                if ($driver === 'sqlite') {
                    $pdo->exec("PRAGMA foreign_keys = OFF;");
                    $pdo->exec("DELETE FROM `{$table}`;");
                    @$pdo->exec("DELETE FROM sqlite_sequence WHERE name = '{$table}';");
                    $pdo->exec("PRAGMA foreign_keys = ON;");
                } elseif ($driver === 'mysql') {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
                    $pdo->exec("TRUNCATE TABLE `{$table}`;");
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                } elseif ($driver === 'pgsql') {
                    $pdo->exec("TRUNCATE TABLE \"{$table}\" RESTART IDENTITY CASCADE;");
                } else {
                    $pdo->exec("DELETE FROM {$table};");
                }
                $this->log("Truncated table '{$table}'.");
            }
        } catch (Throwable $e) {
            $this->log("Truncate warning on tables: " . $e->getMessage());
        }
    }

    /**
     * Synthesizes a realistic fake value for a given field type.
     *
     * @param string $type The data type or contextual name (name, email, phone, city, uuid, date, etc.)
     * @param mixed ...$args Additional arguments
     * @return mixed
     */
    protected function fake(string $type, ...$args): mixed
    {
        $type = strtolower($type);
        $randSuffix = mt_rand(100, 9999);

        return match ($type) {
            'first_name' => SmartDataGenerator::generateRecord(['first_name' => 'first_name'])['first_name'] ?? 'Alex',
            'last_name' => SmartDataGenerator::generateRecord(['last_name' => 'last_name'])['last_name'] ?? 'Morgan',
            'name', 'full_name' => (SmartDataGenerator::generateRecord(['first_name' => 'first_name'])['first_name'] ?? 'Alex') . ' ' . (SmartDataGenerator::generateRecord(['last_name' => 'last_name'])['last_name'] ?? 'Morgan'),
            'email' => "user.{$randSuffix}@example.edu",
            'phone' => '+1 (' . mt_rand(200, 999) . ') ' . mt_rand(100, 999) . '-' . mt_rand(1000, 9999),
            'city' => ['New York', 'San Francisco', 'London', 'Berlin', 'Tokyo', 'Toronto', 'Sydney', 'Mumbai'][array_rand(['New York', 'San Francisco', 'London', 'Berlin', 'Tokyo', 'Toronto', 'Sydney', 'Mumbai'])],
            'uuid' => sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)),
            'date' => date('Y-m-d', time() - mt_rand(0, 365 * 86400)),
            'datetime' => date('Y-m-d H:i:s', time() - mt_rand(0, 365 * 86400)),
            'boolean', 'bool' => (bool)mt_rand(0, 1),
            'integer', 'int' => mt_rand($args[0] ?? 1, $args[1] ?? 100),
            'currency', 'price' => round(mt_rand(10, 1000) + (mt_rand(0, 99) / 100), 2),
            default => "Sample " . ucfirst($type) . " " . $randSuffix,
        };
    }

    /**
     * Appends a log message to the seeder's execution log and echoes to CLI if applicable.
     */
    protected function log(string $message): void
    {
        $timestamp = date('H:i:s');
        $formatted = "[{$timestamp}] {$message}";
        $this->logs[] = $formatted;

        if (PHP_SAPI === 'cli' && !defined('SPP_SUPPRESS_SEEDER_OUTPUT')) {
            echo "  {$formatted}\n";
        }
    }

    /**
     * Retrieves the recorded execution logs.
     *
     * @return array<string>
     */
    public function getLogs(): array
    {
        return $this->logs;
    }
}
