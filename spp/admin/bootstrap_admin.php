<?php
/**
 * SPP Admin Bootstrap Script
 * 
 * This script initializes the administration environment by ensuring a default
 * administrator account exists in the database. 
 * 
 * Usage: php spp/admin/bootstrap_admin.php
 */

require_once dirname(__DIR__, 2) . '/spp/sppinit.php';
require_once dirname(__DIR__, 2) . '/global.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use SPPMod\SPPAuth\SPPUser;
use SPPMod\SPPDB\SPPDB;
use SPP\SPPBase;

try {
    echo "--- SPP Admin Bootstrap (XDB Mode) ---\n";
    
    // Force XDB for administrative identity
    $db = new SPPDB("xdb:dbname=default");
    
    // Ensure tables exist in XDB
    if (!$db->tableExists('users')) {
        echo "Step: Provisioning 'users' table in XDB...\n";
        $db->execute_query("CREATE TABLE users (id AUTO_INCREMENT, username STRING, password STRING, status STRING)");
    }
    if (!$db->tableExists('roles')) {
        echo "Step: Provisioning 'roles' table in XDB...\n";
        $db->execute_query("CREATE TABLE roles (id AUTO_INCREMENT, role_name STRING, description STRING)");
    }
    if (!$db->tableExists('userroles')) {
        echo "Step: Provisioning 'userroles' table in XDB...\n";
        $db->execute_query("CREATE TABLE userroles (userid INT, roleid INT)");
    }

    // Check if admin already exists
    $adminCheck = $db->execute_query("SELECT id FROM users WHERE username='admin'");
    if (!empty($adminCheck)) {
        echo "Check: Administrator 'admin' already exists in XDB.\n";
    } else {
        echo "Step: Creating default administrator 'admin' in XDB...\n";
        
        // Ensure standard 'Admin' role exists
        $roleCheck = $db->execute_query("SELECT id FROM roles WHERE role_name='Admin'");
        if (empty($roleCheck)) {
            echo "Step: Creating 'Admin' role...\n";
            $db->execute_query("INSERT INTO roles (role_name, description) VALUES ('Admin', 'System Administrator with full access')");
            $roleId = 1; // XDB auto-inc starts at 1
        } else {
            $roleId = $roleCheck[0]['id'];
        }
        
        // Create user (plaintext for now, will be hashed if SPPUser::save is used, 
        // but here we do raw insert for simplicity in bootstrap)
        $hashed = password_hash('admin123', PASSWORD_DEFAULT);
        $db->execute_query("INSERT INTO users (username, password, status) VALUES ('admin', '{$hashed}', 'active')");
        $adminId = 1;

        // Assign role
        $db->execute_query("INSERT INTO userroles (userid, roleid) VALUES ({$adminId}, {$roleId})");
        
        echo "Success: Created 'admin' with password 'admin123' and assigned 'Admin' role in XDB.\n";
        echo "IMPORTANT: Please change this password immediately after login.\n";
    }
    
    echo "--- Bootstrap Complete ---\n";
} catch (Exception $e) {
    echo "Fatal Error during bootstrap: " . $e->getMessage() . "\n";
    exit(1);
}
