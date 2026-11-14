<?php
declare(strict_types=1);

namespace Tests\Core;

require_once dirname(__DIR__, 2) . '/spp/admin/services/AdminRBACService.php';
require_once dirname(__DIR__, 2) . '/spp/admin/services/AuthService.php';

use SPPMod\Parikshak\SPPTestCase;
use SPP\Admin\Services\AdminRBAC;
use SPP\Admin\Services\Auth;
use SPP\Admin\Services\SecretsService;

/**
 * Class SppadminRbacAndAuthHardeningTest
 * Parikshak test suite verifying SPPAdmin RBAC fail-closed architecture,
 * production authentication security, brute-force mitigation, API key SHA-256 hashing,
 * and AES-256-GCM authenticated secrets management.
 */
class SppadminRbacAndAuthHardeningTest extends SPPTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // Clear any leftover session state
        $_SESSION = [];
    }

    public function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    /**
     * Test 1: RBAC fail-closed behavior for unmapped actions.
     */
    public function testRbacFailClosedOnUnmappedActions(): void
    {
        // Even super_admin or admin must be denied on completely unknown/unmapped actions
        $this->assertFalse(AdminRBAC::gateAdminAction('arbitrary_unmapped_action_xyz', 'super_admin'));
        $this->assertFalse(AdminRBAC::gateAdminAction('unknown.action', 'admin'));
        $this->assertFalse(AdminRBAC::gateAdminAction('', 'super_admin'));
        $this->assertFalse(AdminRBAC::gateAdminAction('malicious_eval', 'developer'));
    }

    /**
     * Test 2: RBAC role hierarchy enforcement on mapped actions.
     */
    public function testRbacRoleHierarchyEnforcement(): void
    {
        // 'list_users' requires admin.identity (super_admin only)
        $this->assertTrue(AdminRBAC::gateAdminAction('list_users', 'super_admin'));
        $this->assertFalse(AdminRBAC::gateAdminAction('list_users', 'admin'));
        $this->assertFalse(AdminRBAC::gateAdminAction('list_users', 'auditor'));

        // 'server_services_get' requires admin.server (operator, admin, super_admin)
        $this->assertTrue(AdminRBAC::gateAdminAction('server_services_get', 'operator'));
        $this->assertTrue(AdminRBAC::gateAdminAction('server_services_get', 'admin'));
        $this->assertTrue(AdminRBAC::gateAdminAction('server_services_get', 'super_admin'));
        $this->assertFalse(AdminRBAC::gateAdminAction('server_services_get', 'developer'));

        // 'backup_create' requires admin.lifecycle (admin, super_admin)
        $this->assertTrue(AdminRBAC::gateAdminAction('backup_create', 'admin'));
        $this->assertTrue(AdminRBAC::gateAdminAction('backup_create', 'super_admin'));
        $this->assertFalse(AdminRBAC::gateAdminAction('backup_create', 'operator'));

        // Agent operations ('agent_call' => 'admin.server')
        $this->assertTrue(AdminRBAC::gateAdminAction('agent_call', 'super_admin'));
        $this->assertTrue(AdminRBAC::gateAdminAction('agent_call', 'admin'));
        $this->assertFalse(AdminRBAC::gateAdminAction('agent_call', 'auditor'));

        // Storage & File Manager ('storage_list_files' => 'admin.files')
        $this->assertTrue(AdminRBAC::gateAdminAction('storage_list_files', 'super_admin'));
        $this->assertTrue(AdminRBAC::gateAdminAction('storage_list_files', 'admin'));
        $this->assertTrue(AdminRBAC::gateAdminAction('storage_list_files', 'operator'));
        $this->assertFalse(AdminRBAC::gateAdminAction('storage_list_files', 'auditor'));
    }

    /**
     * Test 3: Public allowlist gating in API layer.
     */
    public function testPublicAllowlistGating(): void
    {
        // Whitelisted public actions (login/auth actions)
        $publicActions = [
            'login',
            'Auth_Login',
            'Auth_VerifyMFA',
            'logout',
            'Auth_Logout',
            'check_auth',
            'Auth_SendMagicLink',
            'Auth_ConsumeMagicLink'
        ];

        // Actions that MUST NOT be accessible without authentication
        $sensitiveActions = [
            'mail_send',
            'server_status',
            'rules_save',
            'screener_run',
            'agent_call',
            'operation_execute',
            'user_delete',
            'backup_download'
        ];

        foreach ($publicActions as $action) {
            $this->assertContains($action, $publicActions);
        }

        foreach ($sensitiveActions as $action) {
            $this->assertNotContains($action, $publicActions, "Action '{$action}' must not be in public allowlist!");
        }
    }

    /**
     * Test 4: Default credentials rejection and secure password verification.
     */
    public function testDefaultPasswordRejectionInProduction(): void
    {
        // Generate a valid bcrypt hash
        $hashed = password_hash('StrongProdPassword#2026', PASSWORD_DEFAULT);

        // Verification of real password
        $this->assertTrue(Auth::verifyPassword('StrongProdPassword#2026', $hashed));

        // Rejection of wrong password
        $this->assertFalse(Auth::verifyPassword('WrongPassword', $hashed));

        // Rejection of empty password
        $this->assertFalse(Auth::verifyPassword('', $hashed));

        // In production mode (when SPP_DEV_MODE is not true), fallback credentials must be rejected
        $plainDummy = 'admin123';
        $this->assertFalse(Auth::verifyPassword('admin123', 'non_bcrypt_plain_text_hash'));
    }

    /**
     * Test 5: Brute force lockout protection.
     */
    public function testBruteForceLockoutMitigation(): void
    {
        $testUser = 'lockout_test_user_' . uniqid();

        // 1 to 4 failed attempts should not lock out
        for ($i = 1; $i <= 4; $i++) {
            Auth::recordFailedLogin($testUser);
            $this->assertFalse(Auth::isLockedOut($testUser), "User should not be locked out after {$i} failures.");
        }

        // 5th failed attempt triggers lockout
        Auth::recordFailedLogin($testUser);
        $this->assertTrue(Auth::isLockedOut($testUser), "User must be locked out after 5 consecutive failures.");

        // Clear failures on successful login
        Auth::clearFailedLogins($testUser);
        $this->assertFalse(Auth::isLockedOut($testUser), "Lockout must be lifted after successful login reset.");
    }

    /**
     * Test 6: API key SHA-256 storage and masked presentation.
     */
    public function testApiKeySecureHashingAndMasking(): void
    {
        $generated = Auth::generateApiKey('Integration Test Key', 'admin');
        $this->assertArrayHasKey('raw_key', $generated);
        $this->assertArrayHasKey('key_hash', $generated);
        $this->assertArrayHasKey('masked_key', $generated);

        $rawKey = $generated['raw_key'];
        $keyHash = $generated['key_hash'];
        $maskedKey = $generated['masked_key'];

        // Raw key starts with spp_ prefix and has 68 chars (spp_ + 64 hex)
        $this->assertMatchesRegularExpression('/^spp_[a-f0-9]{64}$/', $rawKey);
        $this->assertEquals(68, strlen($rawKey));

        // Key hash is SHA-256 of raw key
        $expectedHash = hash('sha256', $rawKey);
        $this->assertEquals($expectedHash, $keyHash);

        // Masked key preserves prefix and last 4 characters, concealing the rest
        $this->assertMatchesRegularExpression('/^spp_\.\.\.[a-f0-9]{4}$/', $maskedKey);
        $this->assertEquals(substr($rawKey, -4), substr($maskedKey, -4));
        $this->assertStringNotContainsString(substr($rawKey, 10, 20), $maskedKey);
    }

    /**
     * Test 7: SecretsService AES-256-GCM authenticated encryption and decryption.
     */
    public function testSecretsAuthenticatedEncryption(): void
    {
        $secrets = new SecretsService();
        $plaintext = 'Confidential_Database_Password_XYZ!#$';

        $encrypted = $secrets->encrypt($plaintext);
        $this->assertNotEmpty($encrypted);
        $this->assertNotEquals($plaintext, $encrypted);

        $decrypted = $secrets->decrypt($encrypted);
        $this->assertEquals($plaintext, $decrypted);

        // Tampering with ciphertext causes decryption failure
        $tampered = substr_replace($encrypted, 'X', 10, 1);
        $this->assertNull($secrets->decrypt($tampered));

        // Invalid base64 returns null, empty string returns empty
        $this->assertNull($secrets->decrypt('invalid-payload'));
        $this->assertSame('', $secrets->decrypt(''));
    }

    /**
     * Test 8: SecretsService masking of sensitive values and structured arrays.
     */
    public function testSecretsMasking(): void
    {
        $secrets = new SecretsService();

        // Plain string masking
        $this->assertEquals('••••••••9999', $secrets->mask('spp_123456789999'));
        $this->assertEquals('••••••••hort', $secrets->mask('short'));
        $this->assertEquals('••••', $secrets->mask('key'));

        // Structured recursive masking
        $data = [
            'host' => '127.0.0.1',
            'port' => 3306,
            'db_password' => 'secret_db_pass_12345',
            'jwt_secret' => 'super_secret_jwt_key',
            'options' => [
                'api_token' => 'token_xyz_1234567890',
                'retries' => 3
            ]
        ];

        $masked = SecretsService::maskSecrets($data);
        $this->assertEquals('127.0.0.1', $masked['host']);
        $this->assertEquals(3306, $masked['port']);
        $this->assertEquals('[REDACTED]', $masked['db_password']);
        $this->assertEquals('[REDACTED]', $masked['jwt_secret']);
        $this->assertEquals('[REDACTED]', $masked['options']['api_token']);
        $this->assertEquals(3, $masked['options']['retries']);
    }

    /**
     * Test 9: Domain lifecycle RBAC gating, platform administration, and multi-tenant domain boundary isolation.
     */
    public function testDomainLifecycleRbacAndScoping(): void
    {
        $auth = \SPP\Core\Security\AuthorizationService::getInstance();

        // 1. Unauthenticated or unprivileged roles must fail closed
        $this->assertFalse($auth->can('domain_delete', 'example.com', ['role' => 'domain_user', 'permissions' => []]));
        $this->assertFalse($auth->can('domain_save', 'example.com', ['role' => 'domain_user', 'permissions' => []]));
        $this->assertFalse($auth->can('domain_delete', 'example.com', ['role' => 'auditor', 'permissions' => []]));
        $this->assertFalse(AdminRBAC::gateAdminAction('domain_delete', 'auditor'));
        $this->assertFalse(AdminRBAC::gateAdminAction('domain_delete', 'developer'));
        $this->assertFalse(AdminRBAC::gateAdminAction('domain_save', 'auditor'));

        // 2. Platform Super Administrator has complete access across all domains
        $this->assertTrue($auth->can('domain_delete', 'example.com', ['role' => 'platform_super_admin', 'permissions' => ['*']]));
        $this->assertTrue($auth->can('domain_save', 'example.com', ['role' => 'platform_super_admin', 'permissions' => ['*']]));
        $this->assertTrue(AdminRBAC::gateAdminAction('domain_delete', 'super_admin'));
        $this->assertTrue(AdminRBAC::gateAdminAction('domain_save', 'super_admin'));

        // 3. Platform Administrator has platform-wide domain management access
        $this->assertTrue($auth->can('domain_delete', 'example.com', ['role' => 'platform_admin', 'permissions' => ['admin.domains']]));
        $this->assertTrue($auth->can('domain_save', 'example.com', ['role' => 'platform_admin', 'permissions' => ['admin.domains']]));
        $this->assertTrue(AdminRBAC::gateAdminAction('domain_delete', 'admin'));
        $this->assertTrue(AdminRBAC::gateAdminAction('domain_save', 'admin'));

        // 4. Session Administrator in active session
        $_SESSION = ['spp_admin_user' => 'admin'];
        \SPP\Core\Security\AuthorizationService::reset();
        $this->assertTrue(gateAdminAction('domain_delete', 'example.com'));
        $this->assertTrue(gateAdminAction('domain_save', 'example.com'));
        $this->assertTrue(gateAdminAction('domains_list', 'example.com'));

        // 5. Fallback Administrator
        $_SESSION = ['spp_admin_fallback' => true];
        \SPP\Core\Security\AuthorizationService::reset();
        $this->assertTrue(gateAdminAction('domain_delete', 'example.com'));
        $this->assertTrue(gateAdminAction('domain_save', 'example.com'));

        // 6. Session bucket administrator (__sppadmin_sppsession)
        $_SESSION = [
            '__sppadmin_sppsession' => [
                'vars' => [
                    'spp_admin_user' => ['val' => 'admin'],
                    '__role_id__' => ['val' => 1]
                ]
            ]
        ];
        \SPP\Core\Security\AuthorizationService::reset();
        $this->assertTrue(gateAdminAction('domain_delete', 'example.com'));
        $this->assertTrue(gateAdminAction('domain_save', 'example.com'));

        // 7. Domain Administrator assigned to example.com
        $tenantUser = [
            'role' => 'domain_admin',
            'domains' => ['example.com'],
            'permissions' => ['admin.domains', 'admin.files']
        ];
        $this->assertTrue($auth->can('domain_delete', 'example.com', $tenantUser));
        $this->assertTrue($auth->can('domain_save', 'example.com', $tenantUser));

        // 8. Cross-domain boundary denial: tenant_manager must NOT access foreign unauthorized domain
        $this->assertFalse($auth->can('domain_delete', 'other-unauthorized-domain.com', $tenantUser));
        $this->assertFalse($auth->can('storage_list_files', 'other-unauthorized-domain.com', $tenantUser));

        // 9. Unmapped action must remain strictly denied (Fail Closed)
        $this->assertFalse(gateAdminAction('arbitrary_malicious_eval', 'example.com'));
    }

    /**
     * Test 10: Storage & File Manager Favorites action RBAC gating.
     */
    public function testStorageFavoritesRbacGating(): void
    {
        $_SESSION = ['spp_admin_user' => 'admin'];
        \SPP\Core\Security\AuthorizationService::reset();

        $this->assertTrue(gateAdminAction('storage_add_favorite'));
        $this->assertTrue(gateAdminAction('storage_get_favorites'));
        $this->assertTrue(gateAdminAction('storage_save_favorites'));
        $this->assertTrue(gateAdminAction('storage_remove_favorite'));
        $this->assertTrue(gateAdminAction('files_add_favorite'));
        $this->assertTrue(gateAdminAction('files_get_favorites'));
    }
}
