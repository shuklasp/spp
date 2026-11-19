<?php
declare(strict_types=1);

namespace Tests\Core;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../spp/sppinit.php';
require_once __DIR__ . '/../../spp/modules/optional/parikshak/src/SPPTestCase.php';
require_once __DIR__ . '/../../spp/modules/optional/sppqueue/src/SPPQueue.php';

use SPPMod\Parikshak\SPPTestCase;
use SPP\PolyglotBridge;
use SPP\Polyglot;
use SPP\Mesh;
use SPP\Core\Polyglot\Platform;
use SPP\Core\Polyglot\ProcessRunner;
use SPP\Core\Polyglot\ProcessResult;
use SPP\Core\Polyglot\ExecutionPlan;
use SPP\Core\Polyglot\PolyglotBridgeInterface;
use SPP\Core\Polyglot\PolyglotBridgeFactory;
use SPP\Core\Polyglot\LanguageRegistry;
use SPP\Core\Polyglot\WorkerManager;
use SPP\Core\Polyglot\CompilationManager;
use SPP\Core\Polyglot\PolyglotJob;
use SPP\Core\Polyglot\DSL\FormulaLanguage;
use SPP\Core\Apps\ForeignAppRegistry;
use SPP\Core\Apps\SubpathVirtualizer;
use SPP\Core\Apps\AppSupervisor;

/**
 * Class PolyglotHardeningParikshakTest
 *
 * Comprehensive hardening and integration verification suite covering:
 * - Bridge adapter contract & ExecutionPlan compliance
 * - Execution categories (interpreted, compiled, DSL)
 * - Command injection immunity
 * - Space and Unicode path resilience
 * - Execution deadlines and process termination
 * - Deadlock-free concurrent stream draining
 * - JSON serialization fidelity
 * - Authenticated persistent worker lifecycle
 * - Stale worker state self-healing
 * - Concurrency compilation locks & atomic publication
 * - Managed asynchronous jobs
 * - HMAC SSO claims with replay protection
 * - Service mesh routing and authorization
 */
class PolyglotHardeningParikshakTest extends SPPTestCase
{
    private string $tempDir;
    private array $originalServer;

    public function setUp(): void
    {
        parent::setUp();
        $this->originalServer = $_SERVER;
        $appDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 2);
        $this->tempDir = $appDir . '/var/test_poly_hardening_' . bin2hex(random_bytes(6));
        if (!is_dir($this->tempDir)) {
            @mkdir($this->tempDir, 0777, true);
        }
    }

    public function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        parent::tearDown();
        $this->recursiveDelete($this->tempDir);
        WorkerManager::cleanupStaleWorkers();
    }

    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->recursiveDelete($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public function testBridgeAdaptersContractAndExecutionPlan(): void
    {
        $sharedDir = $this->tempDir;
        $dummyModule = $this->tempDir . '/dummy_module.cpp';
        file_put_contents($dummyModule, "int main() { return 0; }");
        $adapters = ['java', 'dotnet', 'go', 'compiler', 'python', 'rust'];

        foreach ($adapters as $lang) {
            $bridge = PolyglotBridgeFactory::getBridge($lang);
            $this->assertInstanceOf(PolyglotBridgeInterface::class, $bridge);

            $runtimeInfo = ['name' => $lang, 'path' => 'mock_binary_' . $lang];
            $plan = $bridge->getExecutionPlan($dummyModule, 'testFunc', ['a' => 1], $runtimeInfo, $sharedDir);

            $this->assertInstanceOf(ExecutionPlan::class, $plan);
            $this->assertNotEmpty($plan->executable);
            $this->assertTrue(is_array($plan->arguments));
            $this->assertTrue(is_array($plan->environment));
            $this->assertTrue(is_string($plan->inputTransport));
            $this->assertTrue(is_string($plan->outputFormat));
            $this->assertTrue($plan->timeout > 0);

            // Backward compatible getCommand() produces non-empty string
            $cmd = $bridge->getCommand($dummyModule, 'testFunc', ['a' => 1], $runtimeInfo, $sharedDir);
            $this->assertNotEmpty($cmd);
        }
    }

    public function testExecutionCategories(): void
    {
        // 1. DSL Category (In-Process Formula)
        $formula = new FormulaLanguage();
        $dslResult = $formula->execute("TAX(500, 10) + 50");
        $this->assertEquals(100, $dslResult);

        // 2. Interpreted Category (Python)
        $runtimes = LanguageRegistry::discoverRuntimes();
        $python = $runtimes['python'] ?? null;
        if (!empty($python['path'])) {
            $pyScript = $this->tempDir . '/math_calc.py';
            file_put_contents($pyScript, <<<'PY'
def calculate(a, b):
    return {"sum": a + b, "product": a * b}
PY
            );

            $res = PolyglotBridge::call('python', $pyScript, 'calculate', [15, 3]);
            $this->assertTrue($res['success']);
            $this->assertEquals(18, $res['data']['sum']);
            $this->assertEquals(45, $res['data']['product']);
        }
    }

    public function testRuntimeManifestValidationAndCacheInvalidation(): void
    {
        // Manifest Validation
        $validManifest = [
            'id' => 'testlang',
            'name' => 'Test Language',
            'extensions' => ['.tst'],
            'category' => 'interpreted',
            'execution' => ['ephemeral' => ['command' => '{binary} {source}']]
        ];
        $errors = LanguageRegistry::validateManifest($validManifest);
        $this->assertEmpty($errors);

        $invalidManifest = [
            'id' => '',
            'name' => '',
            'extensions' => [],
            'category' => 'invalid_cat'
        ];
        $errors = LanguageRegistry::validateManifest($invalidManifest);
        $this->assertNotEmpty($errors);
        $this->assertTrue(count($errors) >= 3);

        // Cache Management
        $refreshed = LanguageRegistry::refresh();
        $this->assertNotEmpty($refreshed);
        $this->assertTrue(LanguageRegistry::isCacheFresh());

        $cacheFile = LanguageRegistry::getCacheFilePath();
        $this->assertTrue(file_exists($cacheFile));
        $cacheContent = json_decode((string)file_get_contents($cacheFile), true);
        $this->assertEquals(LanguageRegistry::CACHE_VERSION, $cacheContent['version'] ?? '');

        // Explicit cache clear
        $this->assertTrue(LanguageRegistry::clearCache());
        $this->assertFalse(file_exists($cacheFile));
    }

    public function testCommandInjectionPrevention(): void
    {
        // Attempt injection with shell operators in arguments and module paths
        $maliciousArg = 'hello; echo INJECTED_SEMICOLON & echo INJECTED_AMP';
        $maliciousCmd = [
            PHP_BINARY,
            '-r',
            'echo json_encode(["received" => $argv[1]]);',
            $maliciousArg
        ];

        $result = ProcessRunner::run($maliciousCmd, $this->tempDir);
        $this->assertTrue($result->isSuccess());

        $data = $result->getJson();
        $this->assertNotNull($data);
        // The literal malicious string must be received as an argument value, NOT executed!
        $this->assertEquals($maliciousArg, $data['received']);
        $this->assertEquals(json_encode(['received' => $maliciousArg]), trim($result->stdout));
    }

    public function testPathsContainingSpacesAndUnicode(): void
    {
        $unicodeDir = $this->tempDir . '/Folder With Spaces & Unicode 🚀';
        @mkdir($unicodeDir, 0777, true);

        $scriptFile = $unicodeDir . '/script_test.php';
        file_put_contents($scriptFile, <<<'PHP'
<?php
$raw = file_get_contents('php://stdin');
$args = json_decode($raw, true) ?: [];
echo json_encode(['greeting' => 'Namaste ' . ($args[0] ?? 'World'), 'path' => __FILE__]);
PHP
        );

        $cmd = [PHP_BINARY, $scriptFile];
        $result = ProcessRunner::run($cmd, $unicodeDir, [], json_encode(['SPP Polyglot']));

        $this->assertTrue($result->isSuccess());
        $data = $result->getJson();
        $this->assertNotNull($data);
        $this->assertEquals('Namaste SPP Polyglot', $data['greeting']);
        $this->assertTrue(str_contains($data['path'], 'Folder With Spaces & Unicode 🚀'));
    }

    public function testExecutionTimeoutAndReliableProcessTermination(): void
    {
        // Spawns a long-running process with tight 0.5s timeout
        $cmd = [PHP_BINARY, '-r', 'usleep(3000000); echo "SHOULD_NOT_REACH";'];
        $start = microtime(true);
        $result = ProcessRunner::run($cmd, $this->tempDir, [], null, 0.5);
        $duration = microtime(true) - $start;

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->timedOut);
        $this->assertEquals(ProcessRunner::REASON_TIMEOUT, $result->terminationReason);
        $this->assertFalse(str_contains($result->stdout, 'SHOULD_NOT_REACH'));
        $this->assertTrue($duration < 2.5); // Terminated promptly within margin
    }

    public function testConcurrentStreamDrainingAndPipeDeadlockPrevention(): void
    {
        // Generates 120KB to stderr and 120KB to stdout concurrently.
        // Under naive sequential stream_get_contents(), this causes OS pipe deadlocks.
        $drainScript = $this->tempDir . '/drain_test.php';
        file_put_contents($drainScript, <<<'PHP'
<?php
$chunkOut = str_repeat("A", 4096);
$chunkErr = str_repeat("E", 4096);
for ($i = 0; $i < 30; $i++) {
    fwrite(STDOUT, $chunkOut);
    fwrite(STDERR, $chunkErr);
}
PHP
        );

        $cmd = [PHP_BINARY, $drainScript];
        $start = microtime(true);
        $result = ProcessRunner::run($cmd, $this->tempDir, [], null, 10.0);
        $elapsed = microtime(true) - $start;

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(122880, strlen($result->stdout));
        $this->assertEquals(122880, strlen($result->stderr));
        $this->assertTrue($elapsed < 5.0); // Completed without freezing
    }

    public function testOversizedInputAndOutputLimits(): void
    {
        // 1. Oversized Input check
        $maxIn = 1024; // 1KB limit
        $payload = str_repeat('X', 2048);
        $result = ProcessRunner::run([PHP_BINARY, '-r', 'echo "OK";'], $this->tempDir, [], $payload, 10.0, 10485760, $maxIn);
        $this->assertFalse($result->isSuccess());
        $this->assertEquals(ProcessRunner::REASON_INPUT_LIMIT, $result->terminationReason);

        // 2. Oversized Output check
        $maxOut = 2048; // 2KB limit
        $resultOut = ProcessRunner::run([PHP_BINARY, '-r', 'echo str_repeat("Y", 8192);'], $this->tempDir, [], null, 10.0, $maxOut);
        $this->assertFalse($resultOut->isSuccess());
        $this->assertEquals(ProcessRunner::REASON_OUTPUT_LIMIT, $resultOut->terminationReason);
    }

    public function testJsonNullAndNestedDataHandling(): void
    {
        $testPayload = [
            'id' => 101,
            'is_active' => true,
            'is_deleted' => false,
            'metadata' => null,
            'nested' => [
                'tags' => ['polyglot', 'spp', null, 42],
                'attributes' => [
                    'unicode' => '日本語 and हिनदी',
                    'empty_list' => [],
                    'empty_map' => new \stdClass()
                ]
            ]
        ];

        $echoScript = $this->tempDir . '/echo.php';
        file_put_contents($echoScript, <<<'PHP'
<?php
$input = file_get_contents('php://stdin');
$decoded = json_decode($input, true);
echo json_encode(['echo' => $decoded]);
PHP
        );

        $result = ProcessRunner::run([PHP_BINARY, $echoScript], $this->tempDir, [], json_encode($testPayload));
        $this->assertTrue($result->isSuccess());

        $data = $result->getJson();
        $this->assertNotNull($data);
        $echoed = $data['echo'];
        $this->assertEquals(101, $echoed['id']);
        $this->assertTrue($echoed['is_active']);
        $this->assertFalse($echoed['is_deleted']);
        $this->assertNull($echoed['metadata']);
        $this->assertEquals('日本語 and हिनदी', $echoed['nested']['attributes']['unicode']);
    }

    public function testPersistentWorkerLifecycleAndAuthentication(): void
    {
        $runtimes = LanguageRegistry::discoverRuntimes();
        $python = $runtimes['python'] ?? null;
        if (empty($python['path'])) {
            $this->markTestSkipped('Python runtime not discovered.');
            return;
        }

        $serviceFile = $this->tempDir . '/calc_service.py';
        file_put_contents($serviceFile, <<<'PY'
def add(a, b):
    return a + b

def multiply(a, b):
    return a * b

def fail_test():
    raise ValueError("Intentional worker exception for testing")
PY
        );

        // 1. Start worker
        $start = WorkerManager::startWorker($serviceFile, 'python', 'default');
        $this->assertTrue($start['success']);
        $this->assertTrue($start['port'] > 0);
        $this->assertTrue($start['pid'] > 0);
        $this->assertNotEmpty($start['token']);

        // 2. Authorized request
        $res = WorkerManager::callWorker($serviceFile, 'add', [20, 22], 'python');
        $this->assertTrue($res['success']);
        $this->assertEquals(42, $res['data']);

        // 3. Exception in worker returns structured error (does NOT silently swallow)
        $errRes = WorkerManager::callWorker($serviceFile, 'fail_test', [], 'python');
        $this->assertFalse($errRes['success']);
        $this->assertTrue(str_contains($errRes['error'], 'Intentional worker exception'));

        // 4. Unauthorized request over socket (direct socket test with invalid token)
        $fp = fsockopen('127.0.0.1', $start['port'], $errno, $errstr, 2.0);
        $this->assertNotNull($fp);
        fwrite($fp, json_encode([
            'auth_token' => 'invalid_bogus_token',
            'func' => 'add',
            'args' => [1, 2]
        ]) . "\n");
        $rawResp = trim((string)fgets($fp, 4096));
        fclose($fp);

        $authResp = json_decode($rawResp, true);
        $this->assertFalse($authResp['success']);
        $this->assertEquals(401, $authResp['error_code']);

        // 4b. Missing auth token request rejected
        $fp2 = fsockopen('127.0.0.1', $start['port'], $errno, $errstr, 2.0);
        $this->assertNotNull($fp2);
        fwrite($fp2, json_encode([
            'func' => 'add',
            'args' => [1, 2]
        ]) . "\n");
        $rawMissing = trim((string)fgets($fp2, 4096));
        fclose($fp2);
        $missingResp = json_decode($rawMissing, true);
        $this->assertFalse($missingResp['success']);
        $this->assertEquals(401, $missingResp['error_code']);

        // 4c. Function allowlisting rejects illegal function identifier
        $fp3 = fsockopen('127.0.0.1', $start['port'], $errno, $errstr, 2.0);
        $this->assertNotNull($fp3);
        fwrite($fp3, json_encode([
            'auth_token' => $start['token'],
            'func' => 'add; import os; os.system("calc")',
            'args' => []
        ]) . "\n");
        $rawIllegal = trim((string)fgets($fp3, 4096));
        fclose($fp3);
        $illegalResp = json_decode($rawIllegal, true);
        $this->assertFalse($illegalResp['success']);
        $this->assertEquals(400, $illegalResp['error_code']);

        // 5. Graceful shutdown
        $stop = WorkerManager::stopWorker($serviceFile, 'python', 'default');
        $this->assertTrue($stop['success']);
        usleep(200000);
        $this->assertFalse(Platform::isPidAlive($start['pid']));
    }

    public function testStaleStateAndPortFileCleanup(): void
    {
        $daemonsDir = WorkerManager::getDaemonsDir();
        $fakeHash = 'fake_worker_' . bin2hex(random_bytes(6));
        $fakePort = $daemonsDir . '/' . $fakeHash . '.port';
        $fakePid = $daemonsDir . '/' . $fakeHash . '.pid';
        $fakeMeta = $daemonsDir . '/' . $fakeHash . '.meta.json';

        file_put_contents($fakePort, '65432');
        file_put_contents($fakePid, '99999999'); // Non-existent PID
        file_put_contents($fakeMeta, json_encode([
            'hash' => $fakeHash,
            'pid' => 99999999,
            'port' => 65432,
            'auth_token' => 'fake_token',
        ]));

        $cleaned = WorkerManager::cleanupStaleWorkers();
        $this->assertTrue($cleaned >= 1);
        $this->assertFalse(file_exists($fakePort));
        $this->assertFalse(file_exists($fakePid));
        $this->assertFalse(file_exists($fakeMeta));
    }

    public function testConcurrentCompilationMutexAndAtomicPublication(): void
    {
        $sourceFile = $this->tempDir . '/dummy_c_code.cpp';
        file_put_contents($sourceFile, "int main() { return 0; }");

        $fingerprint = CompilationManager::computeFingerprint($sourceFile, 'clang', '17.0', '-O2');
        $this->assertNotEmpty($fingerprint);
        $this->assertEquals(64, strlen($fingerprint));

        $buildResult = CompilationManager::compile(
            'test_cpp',
            $sourceFile,
            function (string $tempOutput) {
                file_put_contents($tempOutput, "MOCK_COMPILED_BINARY_BYTES");
                return new ProcessResult(0, 'Compiled successfully', '', 12.5, false, 'completed');
            },
            'clang',
            '17.0',
            '-O2'
        );

        $this->assertTrue($buildResult['success']);
        $this->assertTrue(file_exists($buildResult['binary']));
        $this->assertFalse(str_contains($buildResult['binary'], '.tmp.'));

        // Subsequent compile with same fingerprint returns cached without invoking compiler
        $cachedResult = CompilationManager::compile(
            'test_cpp',
            $sourceFile,
            function () {
                $this->fail('Compiler callback should not be invoked when fresh cache exists');
            },
            'clang',
            '17.0',
            '-O2'
        );

        $this->assertTrue($cachedResult['success']);
        $this->assertTrue($cachedResult['cached']);
        $this->assertEquals($buildResult['binary'], $cachedResult['binary']);
    }

    public function testManagedAsynchronousJobExecution(): void
    {
        $phpEcho = $this->tempDir . '/job_task.php';
        file_put_contents($phpEcho, <<<'PHP'
<?php
$args = json_decode(file_get_contents('php://stdin'), true);
echo json_encode(['result' => 'Job done with factor ' . ($args[0] ?? 1)]);
PHP
        );

        $jobId = PolyglotBridge::dispatchAsync('php', $phpEcho, 'main', [5]);
        $this->assertNotEmpty($jobId);

        // Check initial state
        $state = PolyglotBridge::getAsyncStatus($jobId);
        $this->assertNotNull($state);
        $this->assertEquals(PolyglotJob::STATUS_QUEUED, $state['status']);

        // Execute job via handler
        $job = new PolyglotJob($state);
        $job->handle();

        // Check completed state
        $completedState = PolyglotBridge::getAsyncStatus($jobId);
        $this->assertEquals(PolyglotJob::STATUS_COMPLETED, $completedState['status']);
        $this->assertNotNull($completedState['result']);

        $resultData = PolyglotBridge::getAsyncResult($jobId);
        $this->assertEquals('Job done with factor 5', $resultData['result']);

        // Test cancellation
        $cancelJobId = PolyglotBridge::dispatchAsync('php', $phpEcho, 'main', [10]);
        $cancelled = PolyglotBridge::cancelAsync($cancelJobId);
        $this->assertTrue($cancelled);
        $cancelState = PolyglotBridge::getAsyncStatus($cancelJobId);
        $this->assertEquals(PolyglotJob::STATUS_CANCELLED, $cancelState['status']);
    }

    public function testHmacSsoClaimVerificationAndReplayPrevention(): void
    {
        $userId = 'user_99';
        $email = 'engineer@spp.internal';
        $token = 'sec_token_' . bin2hex(random_bytes(16));
        $now = time();

        // 1. Valid Signature
        $sigPayload = "{$userId}:{$email}:{$now}";
        $validSig = hash_hmac('sha256', $sigPayload, $token);

        $this->assertTrue(SubpathVirtualizer::verifySsoSignature($userId, $email, $now, $validSig, $token, 300));

        // 2. Expired Signature (Replay Attack Prevention)
        $expiredTs = $now - 600; // 10 minutes ago
        $expiredSig = hash_hmac('sha256', "{$userId}:{$email}:{$expiredTs}", $token);
        $this->assertFalse(SubpathVirtualizer::verifySsoSignature($userId, $email, $expiredTs, $expiredSig, $token, 300));

        // 3. Futuristic Signature (Clock skew > maxAge)
        $futureTs = $now + 500;
        $futureSig = hash_hmac('sha256', "{$userId}:{$email}:{$futureTs}", $token);
        $this->assertFalse(SubpathVirtualizer::verifySsoSignature($userId, $email, $futureTs, $futureSig, $token, 300));

        // 4. Tampered claims
        $this->assertFalse(SubpathVirtualizer::verifySsoSignature('attacker_user', $email, $now, $validSig, $token, 300));
        $this->assertFalse(SubpathVirtualizer::verifySsoSignature($userId, 'attacker@evil.com', $now, $validSig, $token, 300));

        // 5. Tampered secret token
        $this->assertFalse(SubpathVirtualizer::verifySsoSignature($userId, $email, $now, $validSig, 'wrong_token', 300));
    }

    public function testMeshAuthorizationAndErrorMapping(): void
    {
        ForeignAppRegistry::reset();
        $appsManifest = [
            'apps' => [
                'active_app' => [
                    'name' => 'Active Microservice',
                    'type' => 'node',
                    'route' => '/active',
                    'enabled' => true,
                    'port' => 10099
                ],
                'disabled_app' => [
                    'name' => 'Disabled Microservice',
                    'type' => 'python',
                    'route' => '/disabled',
                    'enabled' => false,
                    'port' => 10098
                ]
            ]
        ];

        $manifestFile = $this->tempDir . '/test_apps.yml';
        file_put_contents($manifestFile, \Symfony\Component\Yaml\Yaml::dump($appsManifest, 4, 2));
        ForeignAppRegistry::loadConfig($manifestFile);

        // Active app resolves
        $res = ForeignAppRegistry::resolveApp('active/items/12');
        $this->assertNotNull($res);
        $this->assertEquals('active_app', $res['app_id']);
        $this->assertEquals('/items/12', $res['sub_path']);

        // Disabled app does NOT resolve route
        $disabledRes = ForeignAppRegistry::resolveApp('disabled/status');
        $this->assertNull($disabledRes);

        // Direct call to disabled app throws authorization error
        $this->expectException(\RuntimeException::class, function () {
            Mesh::call('disabled_app', '/status');
        });
    }
}
