<?php
declare(strict_types=1);

namespace Tests\Core;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../spp/sppinit.php';
require_once __DIR__ . '/../../spp/modules/optional/parikshak/src/SPPTestCase.php';
require_once __DIR__ . '/../../spp/admin/services/StorageAdminService.php';
require_once __DIR__ . '/../../spp/admin/services/CommandBridge.php';

use SPPMod\Parikshak\SPPTestCase;
use SPP\Admin\Services\StorageAdminService;
use SPP\Admin\Services\CommandBridge;

/**
 * Class StorageFileManagerFixesTest
 *
 * Verifies the fixes for File Manager GUI:
 * 1. Safe path resolution for drive letters ('C:', 'C:/', '/') without bogus workspace concatenation.
 * 2. Path sanitization in listFiles ensuring no double slashes ('C://').
 * 3. Directory tree expansion for system drive and workspace root.
 * 4. Empty-path listing defaulting to active workspace root with non-empty items.
 * 5. Admin API authentication compatibility across session buckets.
 */
class StorageFileManagerFixesTest extends SPPTestCase
{
    /**
     * Test safe path resolution for Windows drive letters and Unix root.
     */
    public function testSafePathResolutionForRootsAndDrives(): void
    {
        $sysRoot = StorageAdminService::getSystemRoot();

        // 1. Root '/' should resolve to operating system root
        $resolvedRoot = StorageAdminService::resolveSafePath('/');
        $this->assertNotNull($resolvedRoot, "System root '/' must resolve to a valid path.");
        $this->assertTrue(is_dir($resolvedRoot), "System root path must be a valid directory.");

        // 2. Empty string should resolve to SPP workspace root
        $resolvedEmpty = StorageAdminService::resolveSafePath('');
        $this->assertNotNull($resolvedEmpty, "Empty path must resolve to workspace root.");
        $this->assertEquals(realpath(StorageAdminService::getWorkspaceRoot()), realpath($resolvedEmpty));

        // 3. Drive letter without slash on Windows (e.g. 'C:') must resolve to drive root, NOT a subfolder in workspace
        if (DIRECTORY_SEPARATOR !== '/') {
            $drive = substr($sysRoot, 0, 2); // e.g. 'C:'
            $resolvedDrive = StorageAdminService::resolveSafePath($drive);
            $this->assertNotNull($resolvedDrive, "Drive letter '{$drive}' must resolve to valid path.");
            $this->assertTrue(is_dir($resolvedDrive), "Drive '{$drive}' must resolve to an existing directory.");
            $this->assertStringNotContainsString('school1\\' . $drive, $resolvedDrive, "Drive letter must not be concatenated as a subfolder in workspace.");
        }
    }

    /**
     * Test listFiles path sanitization ensures no double slashes in item paths.
     */
    public function testListFilesPathSanitizationNoDoubleSlashes(): void
    {
        $res = StorageAdminService::listFiles('/');
        $this->assertTrue($res['success'], "listFiles('/') must succeed.");
        $this->assertNotEmpty($res['items'], "listFiles('/') must return items for system root.");

        foreach ($res['items'] as $item) {
            $path = $item['path'];
            // Double slash check: cannot have // anywhere
            $this->assertFalse(
                str_contains($path, '//'),
                "Item path '{$path}' must not contain double slashes."
            );
        }
    }

    /**
     * Test getDirectoryTree supports root and drive paths without double slashes.
     */
    public function testDirectoryTreeDriveAndRootSupport(): void
    {
        $treeRes = StorageAdminService::getDirectoryTree('/', 1);
        $this->assertTrue($treeRes['success'], "getDirectoryTree('/') must succeed.");
        $this->assertNotNull($treeRes['tree'], "Directory tree must have root node.");
        $this->assertNotEmpty($treeRes['tree']['children'], "System root tree node must have child folders.");

        foreach ($treeRes['tree']['children'] as $child) {
            $childPath = $child['path'];
            $this->assertFalse(
                str_contains($childPath, '//'),
                "Tree child path '{$childPath}' must not contain double slashes."
            );
        }

        // Test drive letter without trailing slash
        if (DIRECTORY_SEPARATOR !== '/') {
            $sysRoot = StorageAdminService::getSystemRoot();
            $drive = substr($sysRoot, 0, 2); // 'C:'
            $driveTree = StorageAdminService::getDirectoryTree($drive, 1);
            $this->assertTrue($driveTree['success'], "getDirectoryTree('{$drive}') without trailing slash must succeed.");
        }
    }

    /**
     * Test default workspace file listing when path is empty string.
     */
    public function testDefaultWorkspaceListing(): void
    {
        $res = StorageAdminService::listFiles('');
        $this->assertTrue($res['success'], "listFiles('') must succeed.");
        $this->assertGreaterThan(0, $res['total_items'], "Workspace must contain files/folders.");
        $this->assertEquals(
            StorageAdminService::getWorkspaceRoot(),
            $res['current_path'],
            "Default listFiles path must match workspace root."
        );

        $names = array_column($res['items'], 'name');
        $this->assertContains('spp.php', $names, "Workspace items must contain spp.php.");
    }

    /**
     * Test CommandBridge routes storage_list_files and storage_tree seamlessly.
     */
    public function testCommandBridgeStorageDispatch(): void
    {
        $listRes = CommandBridge::handleAction('storage_list_files', ['path' => '']);
        $this->assertTrue($listRes['success'], "CommandBridge storage_list_files must succeed.");
        $this->assertGreaterThan(0, count($listRes['items']), "CommandBridge must return workspace items.");

        $treeRes = CommandBridge::handleAction('storage_tree', ['root' => '/', 'depth' => 1]);
        $this->assertTrue($treeRes['success'], "CommandBridge storage_tree must succeed.");
        $this->assertNotEmpty($treeRes['tree']['children'], "CommandBridge storage_tree must return child nodes.");
    }

    /**
     * Test WebGuard dev mode fallback preserves administrator user without session destruction.
     */
    public function testWebGuardDevModeFallbackPreservesAdmin(): void
    {
        $guard = new \SPPMod\SPPAuth\WebGuard();
        $user = $guard->user();
        $this->assertNotNull($user, "WebGuard::user() must return a user in dev mode.");
        $this->assertFalse($user instanceof \SPPMod\SPPAuth\AnonymousUser, "WebGuard::user() in dev mode must not be AnonymousUser.");
        $this->assertEquals('admin', $user->username, "Default dev mode user must be 'admin'.");
        $this->assertTrue($guard->check(), "WebGuard::check() must return true in dev mode.");
    }

    /**
     * Test StorageAdminService favorites management and safe path resolution.
     */
    public function testStorageFavoritesManager(): void
    {
        $favs = StorageAdminService::getFavorites();
        $this->assertIsArray($favs, "getFavorites() must return an array.");
        $this->assertNotEmpty($favs, "Favorites must not be empty.");

        $ids = array_column($favs, 'id');
        $this->assertContains('fav_workspace', $ids, "Favorites must contain workspace shortcut.");
        $this->assertContains('fav_system_root', $ids, "Favorites must contain system root shortcut.");

        // Test CommandBridge dispatch for favorites
        $bridgeFavs = CommandBridge::handleAction('storage_get_favorites', []);
        $this->assertIsArray($bridgeFavs, "CommandBridge storage_get_favorites must return array.");

        // Add a test favorite
        $workspace = StorageAdminService::getWorkspaceRoot();
        $added = StorageAdminService::addFavorite('Test Custom Fav', $workspace, '⭐');
        $this->assertIsArray($added, "addFavorite must return array of favorites.");

        // Cleanup test favorite
        $cleaned = StorageAdminService::removeFavorite($workspace);
        $this->assertIsArray($cleaned, "removeFavorite must return array of favorites.");

        // Verify RBAC Gate Mapping for Favorites actions
        require_once SPP_BASE_DIR . '/admin/services/AdminRBAC.php';
        $this->assertTrue(function_exists('gateAdminAction'), "gateAdminAction function must exist.");
        $this->assertTrue(gateAdminAction('storage_add_favorite'), "gateAdminAction('storage_add_favorite') must be allowed.");
        $this->assertTrue(gateAdminAction('storage_get_favorites'), "gateAdminAction('storage_get_favorites') must be allowed.");
        $this->assertTrue(gateAdminAction('storage_remove_favorite'), "gateAdminAction('storage_remove_favorite') must be allowed.");
        $this->assertTrue(gateAdminAction('storage_save_favorites'), "gateAdminAction('storage_save_favorites') must be allowed.");
        $this->assertTrue(gateAdminAction('files_add_favorite'), "gateAdminAction('files_add_favorite') must be allowed.");
        $this->assertTrue(gateAdminAction('files_get_favorites'), "gateAdminAction('files_get_favorites') must be allowed.");
    }
}
