<?php
namespace SPPMod\SPPOS;

/**
 * Class KernelGuard
 * 
 * The zero-trust Identity and Access Management (IAM) layer for the WebOS.
 * Intercepts VirtualPDO and VFS operations to ensure apps do not exceed
 * their granted permissions.
 */
class KernelGuard
{
    /**
     * Gets the current guest application ID running in the execution thread.
     */
    public static function getCurrentAppId(): string
    {
        if (defined('SPP_INSTANCE')) {
            return SPP_INSTANCE;
        }
        return 'spp_core';
    }

    /**
     * Verifies if the current app has permission to write data via VirtualPDO.
     */
    public static function canWrite(string $appId, string $query): bool
    {
        // For architectural demonstration, allow all core operations.
        if ($appId === 'spp_core') {
            return true;
        }

        $queryUpper = strtoupper(trim($query));
        
        // Security check: Block direct modifications to the users table
        // if the app was not granted IAM permission.
        if (strpos($queryUpper, 'SPP_USERS') !== false) {
            // Check IAM Registry (mock)
            $iamRegistry = [
                'wordpress:blog1' => ['write_users' => false],
                'magento:store1'  => ['write_users' => true]
            ];

            if (isset($iamRegistry[$appId]) && $iamRegistry[$appId]['write_users'] === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verifies if the current app can read a specific path on the Virtual File System.
     */
    public static function canReadFile(string $appId, string $vfsPath): bool
    {
        // Example: Only Moodle can read /spp://storage/courses
        if (strpos($vfsPath, 'courses') !== false && strpos($appId, 'moodle') === false) {
            return false;
        }

        return true;
    }
}
