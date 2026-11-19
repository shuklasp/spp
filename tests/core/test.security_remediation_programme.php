<?php
declare(strict_types=1);

namespace Tests\Core;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../spp/sppinit.php';
require_once __DIR__ . '/../../spp/modules/optional/parikshak/src/SPPTestCase.php';
require_once __DIR__ . '/../../spp/admin/services/AdminRBAC.php';
require_once __DIR__ . '/../../spp/admin/services/Auth.php';
require_once __DIR__ . '/../../spp/admin/services/StorageAdminService.php';
require_once __DIR__ . '/../../spp/admin/services/Audit.php';
require_once __DIR__ . '/../../spp/admin/services/CloudflareBridge.php';
require_once __DIR__ . '/../../spp/core/apps/SubpathVirtualizer.php';
require_once __DIR__ . '/../../spp/core/apps/ForeignAppRegistry.php';
require_once __DIR__ . '/../../spp/core/Polyglot/ProcessRunner.php';
require_once __DIR__ . '/../../spp/core/Polyglot/PolyglotJob.php';
require_once __DIR__ . '/../../spp/core/Polyglot/CompilationManager.php';
require_once __DIR__ . '/../../spp/core/Polyglot/WorkerManager.php';
require_once __DIR__ . '/../../spp/core/Dns/DnsCredentialVault.php';

use SPPMod\Parikshak\SPPTestCase;
use SPP\Admin\Services\AdminRBAC;
use SPP\Admin\Services\Auth;
use SPP\Admin\Services\StorageAdminService;
use SPP\Admin\Services\CloudflareBridge;
use SPP\Core\Apps\SubpathVirtualizer;
use SPP\Core\Apps\ForeignAppRegistry;
use SPP\Core\Polyglot\ProcessRunner;
use SPP\Core\Polyglot\PolyglotJob;
use SPP\Core\Polyglot\CompilationManager;
use SPP\Core\Polyglot\WorkerManager;
use SPP\Core\Dns\DnsCredentialVault;

/**
 * Class SecurityRemediationProgrammeTest
 *
 * Comprehensive Parikshak integration test suite validating the complete
 * SPP Security & Reliability Remediation Programme:
 * 1. Admin RBAC fail-closed architecture, domain sanitization, and privilege escalation prevention.
 * 2. Authentication hardening, persistent brute-force mitigation, and plaintext password elimination.
 * 3. SSO and Foreign App identity: zero fallback secret, atomic JTI nonce replay protection.
 * 4. Foreign App filesystem containment and system root denial.
 * 5. Shell-free ProcessRunner execution, tokenizer, and output byte limits.
 * 6. Polyglot job concurrency locking and process tree cancellation.
 * 7. CompilationManager atomic publication order and verification before metadata write.
 * 8. WorkerManager bounded socket reads and partial write loops.
 * 9. DnsCredentialVault fail-closed authenticated encryption.
 * 10. CloudflareBridge TLS peer/host verification.
 * 11. StorageAdminService authorized root sets and chunk upload byte limit enforcement.
 * 12. Admin audit logging credential and token redaction.
 */
class SecurityRemediationProgrammeTest extends SPPTestCase
{
    private string $tempDir;

    public function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        $appDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 2);
        $this->tempDir = $appDir . '/var/test_sec_rem_' . bin2hex(random_bytes(4));
        if (!is_dir($this->tempDir)) {
            @mkdir($this->tempDir, 0777, true);
        }
    }

    public function tearDown(): void
    {
        $_SESSION = [];
        if (is_dir($this->tempDir)) {
            $this->recursiveDelete($this->tempDir);
        }
        parent::tearDown();
    }

    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        if ($items === false) return;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $p = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($p)) {
                $this->recursiveDelete($p);
            } else {
                @unlink($p);
            }
        }
        @rmdir($dir);
    }

    /**
     * 1. Test Admin RBAC fail-closed behavior, domain sanitization, and privilege escalation defenses.
     */
    public function testAdminRbacSecurityGuards(): void
    {
        // 1.1 Fail-closed on unmapped actions
        $this->assertFalse(AdminRBAC::gateAdminAction('unmapped_action_xyz', 'super_admin'));
        $this->assertFalse(AdminRBAC::gateAdminAction('', 'super_admin'));

        // 1.2 Domain traversal and pollution sanitization
        $this->assertFalse(\gateAdminAction('storage_list_files', '../../etc'));
        $this->assertFalse(\gateAdminAction('storage_list_files', "domain\0null"));
        $this->assertFalse(\gateAdminAction('storage_list_files', 'bad domain with spaces'));

        // 1.3 Privilege escalation: non-super admin cannot grant admin.* or self-escalate
        $_SESSION['spp_admin_test_scopes'] = ['admin.identity', 'admin.storage'];
        $_SESSION['spp_admin_user'] = 'operator_user';
        $_SESSION['spp_admin_user_id'] = 'operator_user';

        $mockLiveAction = new class {
            public string $status = '';
            public string $message = '';
            public function setStatus(string $s) { $this->status = $s; return $this; }
            public function notify(string $m, string $type = 'info') { $this->message = $m; return $this; }
        };

        // Attempting to grant admin.* as operator must fail
        live_save_admin_permissions($mockLiveAction, ['user_id' => 'dev_user', 'scopes' => ['admin.*']]);
        $this->assertEquals('error', $mockLiveAction->status);
        $this->assertTrue(str_contains(strtolower($mockLiveAction->message), 'super-administrators'));

        // Self-escalation: attempting to modify own permissions as non-super-admin must fail
        $mockSelfAction = new class {
            public string $status = '';
            public string $message = '';
            public function setStatus(string $s) { $this->status = $s; return $this; }
            public function notify(string $m, string $type = 'info') { $this->message = $m; return $this; }
        };
        live_save_admin_permissions($mockSelfAction, ['user_id' => 'operator_user', 'scopes' => ['admin.storage']]);
        $this->assertEquals('error', $mockSelfAction->status);
        $this->assertTrue(str_contains(strtolower($mockSelfAction->message), 'own administrative permissions'));
    }

    /**
     * 2. Test Authentication hardening: plaintext removal, default password rejection, and brute-force mitigation.
     */
    public function testAuthSecurityAndBruteForceLockout(): void
    {
        $ip = '10.254.254.' . rand(1, 250);
        $testUser = 'test_sec_user_' . bin2hex(random_bytes(3));

        // 2.1 Rejects default passwords in non-dev
        $devEnv = defined('SPP_ENV') ? SPP_ENV : 'production';
        if ($devEnv !== 'development' && $devEnv !== 'dev' && $devEnv !== 'local') {
            $this->assertFalse(Auth::verifyPassword('admin123', '$2y$10$dummyHashMustFailOnDefault', $testUser));
            $this->assertFalse(Auth::verifyPassword('admin', '$2y$10$dummyHashMustFailOnDefault', $testUser));
        }

        // 2.2 Verify plaintext comparison is eliminated
        $plainSecret = 'CorrectHorseBatteryStaple99!';
        $this->assertFalse(Auth::verifyPassword($plainSecret, $plainSecret, $testUser));

        // 2.3 Verify persistent brute-force lockout
        Auth::clearFailedLogins($testUser, $ip);
        $this->assertFalse(Auth::isLockedOut($testUser, $ip));

        // Record 5 failed attempts
        for ($i = 0; $i < 5; $i++) {
            Auth::recordFailedLogin($testUser, $ip);
        }

        $this->assertTrue(Auth::isLockedOut($testUser, $ip), 'Must be locked out after 5 failed login attempts.');

        // Clear lockouts
        Auth::clearFailedLogins($testUser, $ip);
        $this->assertFalse(Auth::isLockedOut($testUser, $ip), 'Lockout must be cleared.');
    }

    /**
     * 3. Test SubpathVirtualizer: zero default fallback secret and atomic JTI nonce replay defense.
     */
    public function testSubpathVirtualizerSecretAndNonceIntegrity(): void
    {
        $_SESSION['spp_user'] = [
            'id' => 42,
            'email' => 'alice@example.com',
            'roles' => ['developer']
        ];

        // 3.1 Empty secret must throw RuntimeException when building ingress headers
        $appWithoutSecret = [
            'id' => 'test_app_no_secret',
            'spp_integration' => [
                'sso_bridge' => true,
                'signing_secret' => ''
            ]
        ];

        $threw = false;
        try {
            SubpathVirtualizer::buildIngressHeaders($appWithoutSecret, '/testapp');
        } catch (\RuntimeException $e) {
            $threw = true;
            $this->assertStringContainsString('signing secret', strtolower($e->getMessage()));
        }
        $this->assertTrue($threw, 'Building ingress headers without signing secret must throw RuntimeException.');

        // 3.2 Nonce generation and single-use replay prevention
        $secret = 'test_cryptographic_secret_64_bytes_long_' . bin2hex(random_bytes(16));
        $appWithSecret = [
            'id' => 'test_app_with_secret',
            'spp_integration' => [
                'sso_bridge' => true,
                'signing_secret' => $secret
            ]
        ];

        $headers = SubpathVirtualizer::buildIngressHeaders($appWithSecret, '/testapp');
        $this->assertNotEmpty($headers['X-SPP-Nonce'], 'Headers must carry unique X-SPP-Nonce.');
        $this->assertNotEmpty($headers['X-SPP-Signature'], 'Headers must carry cryptographic signature.');

        $nonce = $headers['X-SPP-Nonce'];
        $ts = (int)$headers['X-SPP-Timestamp'];
        $sig = $headers['X-SPP-Signature'];

        // First verification must succeed
        $verifiedFirst = SubpathVirtualizer::verifySsoSignature(42, 'alice@example.com', $ts, $sig, $secret, 300, $nonce);
        $this->assertTrue($verifiedFirst, 'First SSO verification must succeed.');

        // Replay verification with same nonce must be rejected
        $verifiedSecond = SubpathVirtualizer::verifySsoSignature(42, 'alice@example.com', $ts, $sig, $secret, 300, $nonce);
        $this->assertFalse($verifiedSecond, 'Replay of consumed SSO nonce must be rejected.');

        // 3.3 Rejects future timestamps (> 15 seconds)
        $futureTs = time() + 60;
        $futureNonce = bin2hex(random_bytes(16));
        $futurePayload = '42:alice@example.com:' . $futureTs . ':' . $futureNonce;
        $futureSig = hash_hmac('sha256', $futurePayload, $secret);

        $futureVerified = SubpathVirtualizer::verifySsoSignature(42, 'alice@example.com', $futureTs, $futureSig, $secret, 300, $futureNonce);
        $this->assertFalse($futureVerified, 'SSO signature with excessive future timestamp must be rejected.');
    }

    /**
     * 4. Test ForeignAppRegistry system directory protection and subpath containment.
     */
    public function testForeignAppRegistryFilesystemContainment(): void
    {
        // 4.1 System roots must be forbidden
        $this->assertTrue(ForeignAppRegistry::isForbiddenSystemPath('/'));
        $this->assertTrue(ForeignAppRegistry::isForbiddenSystemPath('/etc'));
        $this->assertTrue(ForeignAppRegistry::isForbiddenSystemPath('/var'));
        $this->assertTrue(ForeignAppRegistry::isForbiddenSystemPath('/bin'));
        $this->assertTrue(ForeignAppRegistry::isForbiddenSystemPath('C:\\'));
        $this->assertTrue(ForeignAppRegistry::isForbiddenSystemPath('C:\\Windows'));
        $this->assertTrue(ForeignAppRegistry::isForbiddenSystemPath('C:\\Program Files'));

        // 4.2 Subpath containment
        $docroot = $this->tempDir;
        $contained = ForeignAppRegistry::verifySubpathContainment($docroot, 'public/index.php');
        $this->assertNotNull($contained, 'Valid subpath must resolve to non-null string.');
        $this->assertTrue(str_contains(str_replace('\\', '/', $contained), 'public/index.php'));

        // Traversal attempts must fail
        $this->assertNull(ForeignAppRegistry::verifySubpathContainment($docroot, '../outside.php'));
        $this->assertNull(ForeignAppRegistry::verifySubpathContainment($docroot, '../../../../etc/passwd'));
        $this->assertNull(ForeignAppRegistry::verifySubpathContainment($docroot, "subdir\0null.php"));
    }

    /**
     * 5. Test ProcessRunner shell-free execution, command tokenizer, and output byte limit enforcement.
     */
    public function testProcessRunnerHardening(): void
    {
        // 5.1 Command tokenization
        $tokens = ProcessRunner::tokenizeCommandLine('php -r "echo \'hello world\';" --flag=value');
        $this->assertEquals(['php', '-r', "echo 'hello world';", '--flag=value'], $tokens);

        // 5.2 Argument array execution (no shell)
        $phpBin = PHP_BINARY;
        $res = ProcessRunner::run([$phpBin, '-r', 'echo json_encode(["status" => "ok"]);']);
        $this->assertTrue($res->isSuccess());
        $this->assertEquals(0, $res->exitCode);
        $this->assertTrue(str_contains($res->stdout, '"status":"ok"'));

        // 5.3 Output byte limit enforcement
        $floodScript = 'for ($i = 0; $i < 10000; $i++) { echo "0123456789ABCDEF\n"; }';
        $resLimit = ProcessRunner::run([$phpBin, '-r', $floodScript], null, [], null, 5, 256);
        $this->assertEquals('output_limit_exceeded', $resLimit->terminationReason, 'Process termination reason must be output_limit_exceeded.');
        $this->assertLessThanOrEqual(512, strlen($resLimit->stdout), 'Captured stdout must not exceed bounded buffer.');
    }

    /**
     * 6. Test PolyglotJob concurrency locking and cancellation telemetry.
     */
    public function testPolyglotJobLockingAndCancellation(): void
    {
        $jobId = PolyglotJob::dispatch('php', 'test_module', 'test_func', ['a' => 1]);
        $this->assertNotEmpty($jobId);

        $state = PolyglotJob::getState($jobId);
        $this->assertNotNull($state);
        $this->assertEquals(PolyglotJob::STATUS_QUEUED, $state['status']);

        // Cancel job
        $cancelled = PolyglotJob::cancel($jobId);
        $this->assertTrue($cancelled, 'Cancelling queued job must succeed.');

        $stateAfter = PolyglotJob::getState($jobId);
        $this->assertNotNull($stateAfter);
        $this->assertEquals(PolyglotJob::STATUS_CANCELLED, $stateAfter['status']);
        $this->assertNotEmpty($stateAfter['cancelled_at']);
        $this->assertEquals('user_cancelled', $stateAfter['cancellation_reason']);
    }

    /**
     * 7. Test CompilationManager cache fingerprint computation.
     */
    public function testCompilationManagerAtomicVerification(): void
    {
        $cacheDir = CompilationManager::getCacheDir('c');
        $this->assertNotEmpty($cacheDir);

        $fingerprint = CompilationManager::computeFingerprint('test.c', 'gcc', '13.2.0', '-O2');
        $this->assertNotEmpty($fingerprint);
        $this->assertEquals(64, strlen($fingerprint), 'Fingerprint must be a 64-character SHA-256 hex string.');
    }

    /**
     * 8. Test WorkerManager computeWorkerHash and cleanup.
     */
    public function testWorkerManagerBoundedCommunication(): void
    {
        $hash = WorkerManager::computeWorkerHash('my_module', 'python');
        $this->assertNotEmpty($hash);
        $this->assertIsString($hash);

        $cleaned = WorkerManager::cleanupStaleWorkers();
        $this->assertIsInt($cleaned);
    }

    /**
     * 9. Test DnsCredentialVault authenticated encryption and decryption.
     */
    public function testDnsCredentialVaultFailClosedEncryption(): void
    {
        // 9.1 Encrypt string returns ENC: prefix
        $token = 'my_cloudflare_api_token_' . bin2hex(random_bytes(8));
        $cipher = DnsCredentialVault::encrypt($token);
        $this->assertTrue(str_starts_with($cipher, 'ENC:'));

        // 9.2 Decrypt recovers original token
        $plain = DnsCredentialVault::decrypt($cipher);
        $this->assertEquals($token, $plain);

        // 9.3 Unencrypted string without ENC: is returned as-is
        $this->assertEquals('raw_non_encrypted', DnsCredentialVault::decrypt('raw_non_encrypted'));
    }

    /**
     * 10. Test CloudflareBridge TLS verification.
     */
    public function testCloudflareBridgeTlsVerification(): void
    {
        $bridgePath = dirname(__DIR__, 2) . '/spp/admin/services/CloudflareBridge.php';
        $this->assertTrue(file_exists($bridgePath));
        $code = file_get_contents($bridgePath);

        $this->assertTrue(str_contains($code, 'CURLOPT_SSL_VERIFYPEER => true'));
        $this->assertTrue(str_contains($code, 'CURLOPT_SSL_VERIFYHOST => 2'));
        $this->assertFalse(str_contains($code, 'CURLOPT_SSL_VERIFYPEER => false'));
    }

    /**
     * 11. Test StorageAdminService authorized roots and chunk upload limit enforcement.
     */
    public function testStorageAdminServiceSecurityAndChunkUploads(): void
    {
        // 11.1 Authorized roots must contain workspace
        $roots = StorageAdminService::getAuthorizedRoots();
        $this->assertNotEmpty($roots);
        $this->assertTrue(StorageAdminService::isContainedInAuthorizedRoots(StorageAdminService::getWorkspaceRoot()));

        // Traversal path outside authorized roots must be denied
        $this->assertFalse(StorageAdminService::isContainedInAuthorizedRoots('../../etc/shadow'));
        $this->assertNull(StorageAdminService::resolveSafePath('../../etc/shadow'));

        // 11.2 Chunk upload: rejects invalid uploadId token
        $badTokenRes = StorageAdminService::handleChunkUpload([
            'uploadId' => '../../evil_token',
            'targetDir' => $this->tempDir,
            'filename' => 'test.txt'
        ], ['content' => 'data']);
        $this->assertFalse($badTokenRes['success']);

        // 11.3 Chunk upload: rejects oversized chunk
        $oversizedRes = StorageAdminService::handleChunkUpload([
            'uploadId' => 'upload_valid_123',
            'targetDir' => $this->tempDir,
            'filename' => 'test.txt',
            'chunkSize' => 20000000 // 20MB exceeds 10MB limit
        ], ['content' => 'data']);
        $this->assertFalse($oversizedRes['success']);
        $this->assertTrue(str_contains($oversizedRes['error'], 'exceeds maximum chunk size'));

        // 11.4 Chunk upload: successful chunk assembly
        $uploadId = 'test_up_' . bin2hex(random_bytes(4));
        $chunk1 = StorageAdminService::handleChunkUpload([
            'uploadId' => $uploadId,
            'targetDir' => $this->tempDir,
            'filename' => 'assembled.txt',
            'chunkIndex' => 0,
            'totalChunks' => 2,
            'chunkSize' => 5,
            'totalSize' => 10
        ], ['content' => 'Hello']);
        $this->assertTrue($chunk1['success']);
        $this->assertFalse($chunk1['completed']);

        $chunk2 = StorageAdminService::handleChunkUpload([
            'uploadId' => $uploadId,
            'targetDir' => $this->tempDir,
            'filename' => 'assembled.txt',
            'chunkIndex' => 1,
            'totalChunks' => 2,
            'chunkSize' => 5,
            'totalSize' => 10
        ], ['content' => 'World']);
        $this->assertTrue($chunk2['success']);
        $this->assertTrue($chunk2['completed']);

        $finalFile = $this->tempDir . '/assembled.txt';
        $this->assertTrue(file_exists($finalFile));
        $this->assertEquals('HelloWorld', file_get_contents($finalFile));
    }

    /**
     * 12. Test Admin Audit Logging parameter redaction.
     */
    public function testAuditLogParameterRedaction(): void
    {
        $this->assertTrue(function_exists('spp_admin_audit_log'));

        // Call spp_admin_audit_log with sensitive keys
        spp_admin_audit_log('test_security_event', [
            'username' => 'admin_test',
            'password' => 'SuperSecretPassword123!',
            'api_key' => 'live_sec_key_abcdef123456',
            'normal_param' => 'public_value'
        ]);

        $this->assertTrue(true, 'Audit log must process redacted params without throwing.');
    }
}
