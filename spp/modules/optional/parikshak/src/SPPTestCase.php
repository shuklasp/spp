<?php

namespace SPPMod\Parikshak;

require_once __DIR__ . '/AssertionFailedError.php';

/**
 * Class SPPTestCase
 * Base class for Parikshak Automated Unit Tests.
 */
abstract class SPPTestCase
{
    use InteractsWithMockery;
    use InteractsWithBrowser;

    protected ?\SPPMod\Parikshak\Fakes\EventFake $eventFake = null;
    protected ?\SPPMod\Parikshak\Fakes\MailFake $mailFake = null;
    protected ?\SPPMod\Parikshak\Fakes\QueueFake $queueFake = null;

    public function setUp(): void
    {
        // Re-initialize DB provider for the current context (e.g. lekhak's in-memory SQLite)
        if (class_exists('\\SPP\\DB')) {
            try {
                \SPP\DB::setProvider(new \SPPMod\SPPDB\SPPDB());
            } catch (\Throwable $e) {
                // Ignore if current context lacks database credentials
            }
        }
    }

    public function tearDown(): void
    {
        // Teardown logic after each test runs
        if ($this->eventFake !== null && class_exists('\SPP\SPPEvent')) {
            \SPP\SPPEvent::setFake(null);
            $this->eventFake = null;
        }
        if ($this->mailFake !== null && class_exists('\SPPMod\SPPMail\MailGateway')) {
            \SPPMod\SPPMail\MailGateway::setFake(null);
            $this->mailFake = null;
        }
        if ($this->queueFake !== null && class_exists('\SPPMod\SPPQueue\SppQueue')) {
            \SPPMod\SPPQueue\SppQueue::setFake(null);
            $this->queueFake = null;
        }
        if (class_exists('\SPPMod\Parikshak\TimeTravel')) {
            \SPPMod\Parikshak\TimeTravel::travelBack();
        }

        if (class_exists('\Mockery')) {
            \Mockery::close();
        }
        $this->verifyNativeMocks();
        $this->closeBrowser();
    }

    protected function assertTrue($condition, string $message = ''): void
    {
        if ($condition !== true) {
            throw new \Exception($message ?: "Failed asserting that value is true.");
        }
    }

    protected function assertFalse($condition, string $message = ''): void
    {
        if ($condition !== false) {
            throw new \Exception($message ?: "Failed asserting that value is false.");
        }
    }

    /**
     * Render a unified terminal diff between expected and actual values.
     */
    protected function renderDiff(mixed $expected, mixed $actual): string
    {
        $expStr = is_string($expected) ? $expected : var_export($expected, true);
        $actStr = is_string($actual) ? $actual : var_export($actual, true);

        $expLines = explode("\n", $expStr);
        $actLines = explode("\n", $actStr);

        $diff = "\n\033[31m--- Expected\033[0m\n\033[32m+++ Actual\033[0m\n";
        $max = max(count($expLines), count($actLines));
        $hasDiff = false;
        for ($i = 0; $i < $max; $i++) {
            $e = $expLines[$i] ?? null;
            $a = $actLines[$i] ?? null;
            if ($e !== $a) {
                $hasDiff = true;
                if ($e !== null) {
                    $diff .= "\033[31m- " . $e . "\033[0m\n";
                }
                if ($a !== null) {
                    $diff .= "\033[32m+ " . $a . "\033[0m\n";
                }
            } else {
                $diff .= "  " . $e . "\n";
            }
        }
        return $hasDiff ? $diff : "";
    }

    protected function assertEquals($expected, $actual, string $message = ''): void
    {
        if ($expected != $actual) {
            $diff = (is_array($expected) || is_array($actual) || is_object($expected) || is_object($actual) || (is_string($expected) && str_contains($expected, "\n")))
                ? $this->renderDiff($expected, $actual)
                : '';
            $expectedStr = is_scalar($expected) ? var_export($expected, true) : gettype($expected);
            $actualStr = is_scalar($actual) ? var_export($actual, true) : gettype($actual);
            $defaultMsg = "Failed asserting that {$actualStr} matches expected {$expectedStr}." . $diff;
            throw new \Exception($message ?: $defaultMsg);
        }
    }

    protected function assertNotEquals($expected, $actual, string $message = ''): void
    {
        if ($expected == $actual) {
            $expectedStr = is_scalar($expected) ? var_export($expected, true) : gettype($expected);
            throw new \Exception($message ?: "Failed asserting that value does not match '{$expectedStr}'.");
        }
    }

    protected function assertSame($expected, $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $diff = (is_array($expected) || is_array($actual) || is_object($expected) || is_object($actual) || (is_string($expected) && str_contains($expected, "\n")))
                ? $this->renderDiff($expected, $actual)
                : '';
            $exp = is_scalar($expected) ? var_export($expected, true) : gettype($expected);
            $act = is_scalar($actual) ? var_export($actual, true) : gettype($actual);
            $defaultMsg = "Failed asserting that values are strictly identical: expected {$exp}, got {$act}." . $diff;
            throw new \Exception($message ?: $defaultMsg);
        }
    }

    protected function assertInstanceOf(string $expected, $actual, string $message = ''): void
    {
        if (!($actual instanceof $expected)) {
            $type = is_object($actual) ? get_class($actual) : gettype($actual);
            throw new \Exception($message ?: "Failed asserting that {$type} is an instance of {$expected}.");
        }
    }

    protected function assertNotInstanceOf(string $expected, $actual, string $message = ''): void
    {
        if ($actual instanceof $expected) {
            $type = is_object($actual) ? get_class($actual) : gettype($actual);
            throw new \Exception($message ?: "Failed asserting that {$type} is not an instance of {$expected}.");
        }
    }

    protected function assertNotNull($actual, string $message = ''): void
    {
        if ($actual === null) {
            throw new \Exception($message ?: "Failed asserting that value is not null.");
        }
    }

    protected function assertNull($actual, string $message = ''): void
    {
        if ($actual !== null) {
            throw new \Exception($message ?: "Failed asserting that value is null.");
        }
    }

    protected function assertCount(int $expectedCount, $countable, string $message = ''): void
    {
        $actualCount = is_countable($countable) ? count($countable) : (is_array($countable) ? count($countable) : 0);
        if ($actualCount !== $expectedCount) {
            throw new \Exception($message ?: "Failed asserting that count {$actualCount} matches expected count {$expectedCount}.");
        }
    }

    protected function assertNotEmpty($actual, string $message = ''): void
    {
        if (empty($actual)) {
            throw new \Exception($message ?: "Failed asserting that value is not empty.");
        }
    }

    protected function assertEmpty($actual, string $message = ''): void
    {
        if (!empty($actual)) {
            throw new \Exception($message ?: "Failed asserting that value is empty.");
        }
    }

    protected function assertGreaterThan($expected, $actual, string $message = ''): void
    {
        if (!($actual > $expected)) {
            throw new \Exception($message ?: "Failed asserting that {$actual} is greater than {$expected}.");
        }
    }

    protected function assertGreaterThanOrEqual($expected, $actual, string $message = ''): void
    {
        if (!($actual >= $expected)) {
            throw new \Exception($message ?: "Failed asserting that {$actual} is greater than or equal to {$expected}.");
        }
    }

    protected function assertLessThan($expected, $actual, string $message = ''): void
    {
        if (!($actual < $expected)) {
            throw new \Exception($message ?: "Failed asserting that {$actual} is less than {$expected}.");
        }
    }

    protected function assertLessThanOrEqual($expected, $actual, string $message = ''): void
    {
        if (!($actual <= $expected)) {
            throw new \Exception($message ?: "Failed asserting that {$actual} is less than or equal to {$expected}.");
        }
    }

    protected function expectException(string $exceptionClass, callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            if ($e instanceof $exceptionClass) {
                return; // Passed
            }
            throw new \Exception("Expected exception {$exceptionClass}, but got " . get_class($e));
        }
        throw new \Exception("Expected exception {$exceptionClass} was not thrown.");
    }

    protected function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new \Exception($message ?: "Failed asserting that '{$haystack}' contains '{$needle}'.");
        }
    }

    protected function assertStringNotContainsString(string $needle, string $haystack, string $message = ''): void
    {
        if (str_contains($haystack, $needle)) {
            throw new \Exception($message ?: "Failed asserting that '{$haystack}' does not contain '{$needle}'.");
        }
    }

    protected function assertArrayHasKey(string|int $key, array|\ArrayAccess $array, string $message = ''): void
    {
        if (!array_key_exists($key, $array)) {
            throw new \Exception($message ?: "Failed asserting that array has key '{$key}'.");
        }
    }

    protected function assertArrayNotHasKey(string|int $key, array|\ArrayAccess $array, string $message = ''): void
    {
        if (array_key_exists($key, $array)) {
            throw new \Exception($message ?: "Failed asserting that array does not have key '{$key}'.");
        }
    }

    protected function assertFileExists(string $filename, string $message = ''): void
    {
        if (!file_exists($filename)) {
            throw new \Exception($message ?: "Failed asserting that file '{$filename}' exists.");
        }
    }

    protected function assertIsArray($actual, string $message = ''): void
    {
        if (!is_array($actual)) {
            $type = gettype($actual);
            throw new \Exception($message ?: "Failed asserting that {$type} is an array.");
        }
    }

    protected function assertMatchesRegularExpression(string $pattern, string $string, string $message = ''): void
    {
        if (!preg_match($pattern, $string)) {
            throw new \Exception($message ?: "Failed asserting that '{$string}' matches regular expression '{$pattern}'.");
        }
    }

    protected function assertContains($needle, $haystack, string $message = ''): void
    {
        if (is_array($haystack)) {
            if (!in_array($needle, $haystack, true)) {
                throw new \Exception($message ?: "Failed asserting that array contains expected element.");
            }
        } elseif (is_string($haystack) && is_string($needle)) {
            if (!str_contains($haystack, $needle)) {
                throw new \Exception($message ?: "Failed asserting that string contains '{$needle}'.");
            }
        } else {
            throw new \Exception($message ?: "Failed asserting that haystack contains needle.");
        }
    }

    protected function assertNotContains($needle, $haystack, string $message = ''): void
    {
        if (is_array($haystack)) {
            if (in_array($needle, $haystack, true)) {
                throw new \Exception($message ?: "Failed asserting that array does not contain element.");
            }
        } elseif (is_string($haystack) && is_string($needle)) {
            if (str_contains($haystack, $needle)) {
                throw new \Exception($message ?: "Failed asserting that string does not contain '{$needle}'.");
            }
        } else {
            throw new \Exception($message ?: "Failed asserting that haystack does not contain needle.");
        }
    }

    /**
     * Assert that a file has registered metadata in its directory __spp_file_props.yml manifest.
     */
    protected function assertFileHasProps(string $path, string $message = ''): void
    {
        if (!class_exists('\\SPPMod\\SPPStorage\\FilePropsManager')) {
            throw new \Exception("FilePropsManager module is not available.");
        }
        $props = \SPPMod\SPPStorage\FilePropsManager::getFileProps($path);
        if ($props === null || empty($props['has_props'])) {
            throw new \Exception($message ?: "Failed asserting that file '{$path}' has registered metadata in __spp_file_props.yml.");
        }
    }

    /**
     * Assert that a file has a specific stability lifecycle state (e.g. 'stable', 'beta', 'deprecated').
     */
    protected function assertFileStability(string $path, string $expectedStability, string $message = ''): void
    {
        if (!class_exists('\\SPPMod\\SPPStorage\\FilePropsManager')) {
            throw new \Exception("FilePropsManager module is not available.");
        }
        $props = \SPPMod\SPPStorage\FilePropsManager::getFileProps($path);
        if ($props === null || empty($props['has_props'])) {
            throw new \Exception("File '{$path}' has no registered metadata in __spp_file_props.yml.");
        }
        $actual = $props['stability'] ?? 'unknown';
        if ($actual !== $expectedStability) {
            throw new \Exception($message ?: "Failed asserting that file '{$path}' has stability '{$expectedStability}', found '{$actual}'.");
        }
    }

    /**
     * Assert that the provided data payload satisfies the required_props contract declared in the partial's manifest.
     */
    protected function assertPartialContract(string $view, array $data, string $message = ''): void
    {
        if (!class_exists('\\SPPMod\\SPPStorage\\FilePropsManager')) {
            throw new \Exception("FilePropsManager module is not available.");
        }
        $filePath = $view;
        if (!file_exists($filePath) && class_exists('\\SPPMod\\SPPView\\ViewLocator')) {
            $app = class_exists('\\SPP\\Scheduler') ? \SPP\Scheduler::getContext() : null;
            $located = \SPPMod\SPPView\ViewLocator::locate($view, $app);
            if ($located) {
                $filePath = $located;
            }
        }
        $props = \SPPMod\SPPStorage\FilePropsManager::getFileProps($filePath);
        if ($props === null) {
            return; // No manifest properties to validate contract against
        }
        $required = $props['attributes']['required_props'] ?? [];
        if (!empty($required) && is_array($required)) {
            $missing = [];
            foreach ($required as $prop) {
                if (!array_key_exists($prop, $data)) {
                    $missing[] = $prop;
                }
            }
            if (!empty($missing)) {
                throw new \Exception($message ?: "Failed asserting partial contract for '{$view}'. Missing required props: " . implode(', ', $missing));
            }
        }
    }

    /**
     * Assert that two JSON strings are logically equivalent.
     */
    protected function assertJsonStringEqualsJsonString(string $expectedJson, string $actualJson, string $message = ''): void
    {
        $exp = json_decode($expectedJson, true);
        if ($exp === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Expected value is not valid JSON: " . json_last_error_msg());
        }
        $act = json_decode($actualJson, true);
        if ($act === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Actual value is not valid JSON: " . json_last_error_msg());
        }
        $this->assertEquals($exp, $act, $message ?: "Failed asserting that two JSON strings encode equivalent structures.");
    }

    protected function assertIsString($actual, string $message = ''): void
    {
        if (!is_string($actual)) {
            $type = gettype($actual);
            throw new \Exception($message ?: "Failed asserting that {$type} is a string.");
        }
    }

    protected function assertIsInt($actual, string $message = ''): void
    {
        if (!is_int($actual)) {
            $type = gettype($actual);
            throw new \Exception($message ?: "Failed asserting that {$type} is an int.");
        }
    }

    protected function assertIsFloat($actual, string $message = ''): void
    {
        if (!is_float($actual)) {
            $type = gettype($actual);
            throw new \Exception($message ?: "Failed asserting that {$type} is a float.");
        }
    }

    protected function assertIsBool($actual, string $message = ''): void
    {
        if (!is_bool($actual)) {
            $type = gettype($actual);
            throw new \Exception($message ?: "Failed asserting that {$type} is a boolean.");
        }
    }

    protected function assertDirectoryExists(string $directory, string $message = ''): void
    {
        if (!is_dir($directory)) {
            throw new \Exception($message ?: "Failed asserting that directory '{$directory}' exists.");
        }
    }

    protected function assertDirectoryDoesNotExist(string $directory, string $message = ''): void
    {
        if (is_dir($directory)) {
            throw new \Exception($message ?: "Failed asserting that directory '{$directory}' does not exist.");
        }
    }

    /**
     * Assert that a matching record exists in the database table.
     */
    protected function assertDatabaseHas(string $table, array $data, string $message = ''): void
    {
        if (!class_exists('\SPP\DB')) {
            throw new \Exception("SPP\\DB provider is not loaded.");
        }

        $where = [];
        $params = [];
        foreach ($data as $col => $val) {
            $where[] = "`{$col}` = ?";
            $params[] = $val;
        }

        $sql = "SELECT COUNT(*) as cnt FROM `{$table}` WHERE " . implode(' AND ', $where);
        $provider = \SPP\DB::getProvider();
        $res = $provider->execute_query($sql, $params);
        $count = $res[0]['cnt'] ?? 0;

        if ($count < 1) {
            $expectedStr = json_encode($data);
            throw new \Exception($message ?: "Failed asserting that table '{$table}' contains matching record: {$expectedStr}");
        }
    }

    /**
     * Assert that a matching record does NOT exist in the database table.
     */
    protected function assertDatabaseMissing(string $table, array $data, string $message = ''): void
    {
        if (!class_exists('\SPP\DB')) {
            throw new \Exception("SPP\\DB provider is not loaded.");
        }

        $where = [];
        $params = [];
        foreach ($data as $col => $val) {
            $where[] = "`{$col}` = ?";
            $params[] = $val;
        }

        $sql = "SELECT COUNT(*) as cnt FROM `{$table}` WHERE " . implode(' AND ', $where);
        $provider = \SPP\DB::getProvider();
        $res = $provider->execute_query($sql, $params);
        $count = $res[0]['cnt'] ?? 0;

        if ($count > 0) {
            $expectedStr = json_encode($data);
            throw new \Exception($message ?: "Failed asserting that table '{$table}' is missing record: {$expectedStr}");
        }
    }

    /**
     * Assert that the value matches its recorded snapshot.
     */
    protected function assertMatchesSnapshot(mixed $actual, ?string $snapshotName = null): void
    {
        SnapshotManager::assertMatches($actual, $snapshotName);
    }

    /**
     * Assert that an entity satisfies all model contracts (CRUD lifecycle, constraints, timestamps, and auditability).
     *
     * @param string $entityClass Full entity class name
     * @param array $customAttributes Optional custom attribute values to override defaults
     */
    protected function assertModelContracts(string $entityClass, array $customAttributes = []): void
    {
        if (!class_exists($entityClass)) {
            throw new \Exception("Entity class '{$entityClass}' does not exist.");
        }

        $table = $entityClass::getMetadata('table');
        if (empty($table)) {
            throw new \Exception("Entity '{$entityClass}' lacks database table mapping in metadata.");
        }

        $metadataAttributes = $entityClass::getMetadata('attributes') ?: [];
        $data = $customAttributes;

        // Auto-populate required fields if missing
        foreach ($metadataAttributes as $col => $type) {
            if ($col === 'id' || array_key_exists($col, $data)) {
                continue;
            }
            if ($col === 'created_at' || $col === 'updated_at') {
                continue;
            }
            if (str_contains(strtolower($type), 'int')) {
                $data[$col] = rand(10, 100);
            } elseif (str_contains(strtolower($type), 'bool')) {
                $data[$col] = true;
            } elseif (str_contains(strtolower($type), 'date')) {
                $data[$col] = date('Y-m-d H:i:s');
            } else {
                $data[$col] = 'Test_' . ucfirst($col);
            }
        }

        // 1. Create & Save
        /** @var \SPPMod\SPPEntity\SPPEntity $entity */
        $entity = new $entityClass();
        foreach ($data as $k => $v) {
            $entity->set($k, $v);
        }
        $saveResult = $entity->save();
        $this->assertTrue((bool)$saveResult, "Failed asserting that entity {$entityClass} saves to {$table}.");

        $id = $entity->get('id');
        $this->assertNotEmpty($id, "Failed asserting that entity {$entityClass} populated an ID upon saving.");

        // 2. Read
        $found = $entityClass::find($id);
        $this->assertNotNull($found, "Failed asserting that entity {$entityClass} with ID {$id} can be found in {$table}.");

        // 3. Update
        $editableCol = null;
        foreach ($metadataAttributes as $col => $type) {
            if ($col !== 'id' && $col !== 'created_at' && $col !== 'updated_at' && (str_contains(strtolower($type), 'varchar') || str_contains(strtolower($type), 'string') || str_contains(strtolower($type), 'text'))) {
                $editableCol = $col;
                break;
            }
        }

        if ($editableCol) {
            $updatedVal = 'Updated_' . uniqid();
            $found->set($editableCol, $updatedVal);
            $found->save();

            $reloaded = $entityClass::find($id);
            $this->assertEquals($updatedVal, $reloaded->get($editableCol), "Failed asserting that entity update persisted.");
        }

        // 4. Delete
        $deleteResult = $found->delete();
        $this->assertTrue((bool)$deleteResult, "Failed asserting that entity {$entityClass} deletes cleanly.");
        $deleted = $entityClass::find($id);
        $this->assertNull($deleted, "Failed asserting that deleted entity {$entityClass} is removed from database.");
    }

    /**
     * Assert that an entity progresses correctly through a declared workflow transition matrix.
     *
     * @param object $entity The workflowable entity
     * @param array<string, string> $transitions Map of ['transition_name' => 'expected_resulting_state']
     */
    protected function assertWorkflowLifecycle(object $entity, array $transitions): void
    {
        if (!method_exists($entity, 'canTransition') || !method_exists($entity, 'applyTransition')) {
            throw new \Exception("Target entity does not implement SPP workflow capabilities.");
        }

        foreach ($transitions as $transitionName => $expectedState) {
            $can = $entity->canTransition($transitionName);
            $currentState = method_exists($entity, 'getWorkflowState') ? $entity->getWorkflowState() : 'unknown';
            $this->assertTrue($can, "Failed asserting that entity can execute transition '{$transitionName}' from state '{$currentState}'.");

            $success = $entity->applyTransition($transitionName, 'tester', 'Automated lifecycle assertion.');
            $this->assertTrue($success, "Failed applying transition '{$transitionName}'.");

            $newState = $entity->getWorkflowState();
            $this->assertEquals($expectedState, $newState, "Failed asserting that transition '{$transitionName}' resulted in state '{$expectedState}'.");
        }

        if (method_exists($entity, 'getWorkflowHistory')) {
            $history = $entity->getWorkflowHistory();
            $this->assertNotEmpty($history, "Failed asserting that workflow execution recorded audit history.");
        }
    }

    /**
     * Intercept and spy on all SPPEvent dispatches and hooks.
     */
    public function fakeEvents(): \SPPMod\Parikshak\Fakes\EventFake
    {
        require_once __DIR__ . '/Fakes/EventFake.php';
        $this->eventFake = new \SPPMod\Parikshak\Fakes\EventFake();
        if (class_exists('\SPP\SPPEvent')) {
            \SPP\SPPEvent::setFake($this->eventFake);
        }
        return $this->eventFake;
    }

    /**
     * Intercept and spy on all outbound email deliveries via MailGateway.
     */
    public function fakeMail(): \SPPMod\Parikshak\Fakes\MailFake
    {
        require_once __DIR__ . '/Fakes/MailFake.php';
        $this->mailFake = new \SPPMod\Parikshak\Fakes\MailFake();
        if (class_exists('\SPPMod\SPPMail\MailGateway')) {
            \SPPMod\SPPMail\MailGateway::setFake($this->mailFake);
        }
        return $this->mailFake;
    }

    /**
     * Instantiate and persist an entity model with synthetic defaults and overrides.
     */
    public function createOne(string $entityClass, array $overrides = []): object
    {
        $entity = $this->makeOne($entityClass, $overrides);
        if (method_exists($entity, 'save')) {
            $entity->save();
        }
        return $entity;
    }

    /**
     * Instantiate and persist multiple entity models with synthetic defaults and overrides.
     */
    public function createMany(string $entityClass, int $count, array $overrides = []): array
    {
        $records = [];
        for ($i = 0; $i < $count; $i++) {
            $records[] = $this->createOne($entityClass, $overrides);
        }
        return $records;
    }

    /**
     * Instantiate an entity model in-memory with synthetic defaults and overrides without persisting.
     */
    public function makeOne(string $entityClass, array $overrides = []): object
    {
        if (!class_exists($entityClass)) {
            throw new \InvalidArgumentException("Entity class [{$entityClass}] does not exist.");
        }

        require_once __DIR__ . '/Faker/SPPFaker.php';
        $faker = new \SPPMod\Parikshak\Faker\SPPFaker();
        $entity = new $entityClass();

        $defaults = [
            'name' => $faker->name(),
            'email' => $faker->email(),
            'title' => ucfirst($faker->text(5)),
            'description' => $faker->text(12),
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        foreach ($defaults as $key => $val) {
            if (method_exists($entity, 'hasField') && $entity->hasField($key)) {
                $entity->set($key, $val);
            } elseif (property_exists($entity, $key)) {
                if (method_exists($entity, 'set')) {
                    $entity->set($key, $val);
                } else {
                    $entity->$key = $val;
                }
            }
        }

        foreach ($overrides as $k => $v) {
            if (method_exists($entity, 'set')) {
                $entity->set($k, $v);
            } else {
                $entity->$k = $v;
            }
        }

        return $entity;
    }

    /**
     * Intercept and spy on all background job dispatches via SppQueue.
     */
    public function fakeQueue(): \SPPMod\Parikshak\Fakes\QueueFake
    {
        require_once __DIR__ . '/Fakes/QueueFake.php';
        $this->queueFake = new \SPPMod\Parikshak\Fakes\QueueFake();
        if (class_exists('\SPPMod\SPPQueue\SppQueue')) {
            \SPPMod\SPPQueue\SppQueue::setFake($this->queueFake);
        }
        return $this->queueFake;
    }

    /**
     * Freeze virtual time to a specific datetime string or object.
     */
    public function freezeTime(string|\DateTimeInterface|null $time = null): \DateTimeImmutable
    {
        require_once __DIR__ . '/TimeTravel.php';
        return \SPPMod\Parikshak\TimeTravel::freeze($time);
    }

    /**
     * Travel relatively by adding/subtracting seconds to the active virtual clock.
     */
    public function travel(int $seconds): \DateTimeImmutable
    {
        require_once __DIR__ . '/TimeTravel.php';
        return \SPPMod\Parikshak\TimeTravel::travel($seconds);
    }

    /**
     * Travel to an absolute target datetime.
     */
    public function travelTo(string|\DateTimeInterface $target): \DateTimeImmutable
    {
        require_once __DIR__ . '/TimeTravel.php';
        return \SPPMod\Parikshak\TimeTravel::travelTo($target);
    }

    /**
     * Reset the virtual clock and return to real system time.
     */
    public function travelBack(): void
    {
        if (class_exists('\SPPMod\Parikshak\TimeTravel')) {
            \SPPMod\Parikshak\TimeTravel::travelBack();
        }
    }

    /**
     * Build and evaluate a declarative architecture rule.
     */
    public function arch(string $description): \SPPMod\Parikshak\Arch\ArchExpectation
    {
        require_once __DIR__ . '/Arch/ArchExpectation.php';
        return new \SPPMod\Parikshak\Arch\ArchExpectation($description);
    }

    /**
     * Audit and verify that all declared GET routes in an application context render cleanly without 500 errors.
     *
     * @param string|null $appName Target app context name (defaults to active scheduler context)
     * @param array $ignoreRoutes URIs or patterns to skip
     */
    public function assertAllGetRoutesPass(?string $appName = null, array $ignoreRoutes = []): void
    {
        $context = $appName ?: (\SPP\Scheduler::getContext() ?: 'default');
        $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : (defined('SPP_APP_DIR') ? SPP_APP_DIR : getcwd());

        $discoveredRoutes = [];
        $compiledCache = $baseDir . "/var/cache/routes_{$context}.php";
        if (file_exists($compiledCache)) {
            $routes = @include $compiledCache;
            if (is_array($routes)) {
                $discoveredRoutes = $routes;
            }
        }

        if (empty($discoveredRoutes) && class_exists('\SPPMod\SPPView\RouteScanner')) {
            $controllersDir = $baseDir . "/src/{$context}/Controllers";
            if (is_dir($controllersDir)) {
                $discoveredRoutes = \SPPMod\SPPView\RouteScanner::scan($controllersDir);
            }
        }

        if (empty($discoveredRoutes)) {
            $pagesYml = $baseDir . "/src/{$context}/etc/pages.yml";
            if (file_exists($pagesYml) && function_exists('yaml_parse_file')) {
                $parsed = @yaml_parse_file($pagesYml);
                if (is_array($parsed)) {
                    foreach (array_keys($parsed) as $route) {
                        $discoveredRoutes[$route] = ['method' => 'GET'];
                    }
                }
            }
        }

        if (empty($discoveredRoutes)) {
            $discoveredRoutes['/'] = ['method' => 'GET'];
        }

        $failures = [];
        $testedCount = 0;

        foreach ($discoveredRoutes as $uri => $def) {
            $method = strtoupper($def['method'] ?? $def['http_method'] ?? 'GET');
            if ($method !== 'GET' && $method !== 'ANY') {
                continue;
            }

            foreach ($ignoreRoutes as $ign) {
                if (str_starts_with($uri, $ign) || $uri === $ign) {
                    continue 2;
                }
            }

            $cleanUri = preg_replace('/\{[a-zA-Z0-9_]+\}/', '1', $uri);
            $cleanUri = preg_replace('/:[a-zA-Z0-9_]+/', '1', $cleanUri);
            if (!str_starts_with($cleanUri, '/')) {
                $cleanUri = '/' . $cleanUri;
            }

            $testedCount++;
            try {
                if (!trait_exists('SPPMod\Parikshak\Traits\InteractsWithApi', false)) {
                    class_alias(\SPPMod\Parikshak\InteractsWithApi::class, 'SPPMod\Parikshak\Traits\InteractsWithApi');
                }
                $api = new class extends \SPPMod\Parikshak\SPPTestCase {
                    use \SPPMod\Parikshak\InteractsWithApi;
                };
                $response = $api->get($cleanUri);
                $status = $response->getStatusCode();

                if ($status >= 500) {
                    $failures[] = "Route [{$cleanUri}] failed with HTTP {$status} (Server Error).";
                }
            } catch (\Throwable $e) {
                $failures[] = "Route [{$cleanUri}] threw uncaught exception: " . $e->getMessage();
            }
        }

        if (!empty($failures)) {
            throw new \Exception("Application route smoke audit failed ({$testedCount} routes audited):\n" . implode("\n", array_slice($failures, 0, 10)));
        }

        $this->assertTrue($testedCount > 0, "No GET routes were discovered to audit for [{$context}].");
    }

    /**
     * Start a property-based test by supplying input generators.
     */
    public function forAll(...$generators): \SPPMod\Parikshak\Fuzz\PropertyRunner
    {
        require_once __DIR__ . '/Fuzz/Fuzz.php';
        require_once __DIR__ . '/Fuzz/PropertyRunner.php';
        return new \SPPMod\Parikshak\Fuzz\PropertyRunner($generators);
    }

    /**
     * Assert that an action completes within the specified execution time budget.
     */
    public function assertExecutionTimeLessThan(float|int $maxMilliseconds, callable $action, string $message = ''): void
    {
        $start = hrtime(true);
        $action();
        $elapsedMs = (hrtime(true) - $start) / 1e6;

        if ($elapsedMs > $maxMilliseconds) {
            $msg = $message ?: sprintf("Execution time exceeded budget: %.2fms elapsed (max allowed: %.2fms).", $elapsedMs, (float)$maxMilliseconds);
            throw new \SPPMod\Parikshak\AssertionFailedError($msg);
        }
        $this->assertTrue(true);
    }

    /**
     * Assert that an action triggers fewer than $maxQueries database queries.
     */
    public function assertDatabaseQueryCountLessThan(int $maxQueries, callable $action, string $message = ''): void
    {
        $startCount = class_exists('\SPP\DB') ? \SPP\DB::getQueryCount() : 0;
        $action();
        $endCount = class_exists('\SPP\DB') ? \SPP\DB::getQueryCount() : 0;
        $queriesUsed = $endCount - $startCount;

        if ($queriesUsed >= $maxQueries) {
            $msg = $message ?: "Database query budget exceeded: {$queriesUsed} queries executed (max allowed: < {$maxQueries}).";
            throw new \SPPMod\Parikshak\AssertionFailedError($msg);
        }
        $this->assertTrue(true);
    }

    /**
     * Assert that peak memory consumption during an action stays below the specified threshold.
     */
    public function assertMemoryPeakLessThan(string|int $maxMemory, callable $action, string $message = ''): void
    {
        $maxBytes = $this->parseMemoryStringToBytes($maxMemory);
        $memBefore = memory_get_usage();
        $action();
        $peakUsed = memory_get_peak_usage() - $memBefore;

        if ($peakUsed > $maxBytes) {
            $humanActual = $this->formatBytesRepresentation($peakUsed);
            $humanMax = is_string($maxMemory) ? $maxMemory : $this->formatBytesRepresentation($maxBytes);
            $msg = $message ?: "Memory budget exceeded: {$humanActual} peak used (max allowed: {$humanMax}).";
            throw new \SPPMod\Parikshak\AssertionFailedError($msg);
        }
        $this->assertTrue(true);
    }

    /**
     * Retry an action up to $times before failing.
     */
    public function retry(int $times, callable $callback, int $delayMs = 0): mixed
    {
        $lastException = null;
        for ($attempt = 1; $attempt <= $times; $attempt++) {
            try {
                return $callback($attempt);
            } catch (\Throwable $e) {
                $lastException = $e;
                if ($attempt < $times && $delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            }
        }
        throw $lastException;
    }

    private function parseMemoryStringToBytes(string|int $mem): int
    {
        if (is_int($mem)) return $mem;
        $unit = strtoupper(substr($mem, -2));
        $val = (float)substr($mem, 0, -2);
        if ($unit === 'KB') return (int)($val * 1024);
        if ($unit === 'MB') return (int)($val * 1024 * 1024);
        if ($unit === 'GB') return (int)($val * 1024 * 1024 * 1024);
        return (int)$mem;
    }

    private function formatBytesRepresentation(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . 'MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . 'KB';
        }
        return $bytes . 'B';
    }
}


