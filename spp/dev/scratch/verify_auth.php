<?php
/**
 * Verification script for SPPAuth Modernization
 */
require_once __DIR__ . '/../../../spp/sppinit.php';

use SPPMod\SPPAuth\SPPAuth;
use SPPMod\SPPAuth\AnonymousUser;

echo "--- SPPAuth Verification ---\n";

// 1. Check Unlogged User
$user = SPPAuth::user();
echo "Current User Type: " . get_class($user) . "\n";
echo "Current User ID: " . $user->getId() . "\n";

if ($user instanceof AnonymousUser) {
    echo "PASS: Unlogged user is AnonymousUser.\n";
} else {
    echo "FAIL: Unlogged user is NOT AnonymousUser.\n";
}

// 2. Check Anonymous Rights
$canView = SPPAuth::can('view_content');
$canPublish = SPPAuth::can('publish_document');

echo "Can view_content (Anonymous): " . ($canView ? "YES" : "NO") . "\n";
echo "Can publish_document (Anonymous): " . ($canPublish ? "YES" : "NO") . "\n";

if ($canView && !$canPublish) {
    echo "PASS: Anonymous rights resolved correctly.\n";
} else {
    echo "FAIL: Anonymous rights resolution error.\n";
}

// 3. Mock Logged In User (Authenticated)
echo "\n--- Mocking Authenticated User ---\n";

if (!class_exists('MockUser')) {
    class MockUser
    {
        public $id;
        public function __construct($id)
        {
            $this->id = $id;
        }
        public function getId()
        {
            return $this->id;
        }
    }
}

\SPP\SPPSession::setSessionVar('__sppauth_user__', 101); // Mock user ID 101
$guard = SPPAuth::guard('web');
$reflection = new ReflectionClass($guard);
$property = $reflection->getProperty('user');
$property->setAccessible(true);

// We need to trick WebGuard to return our MockUser
$property->setValue($guard, new MockUser(101));

$propertyP = $reflection->getProperty('permissionCache');
$propertyP->setAccessible(true);
$propertyP->setValue($guard, []); // Clear cached permissions

$user = SPPAuth::user();
echo "New User ID: " . $user->getId() . " (" . get_class($user) . ")\n";
echo "Is Authenticated: " . (SPPAuth::check() ? "YES" : "NO") . "\n";

$canVote = SPPAuth::can('vote');
$canView = SPPAuth::can('view_content');
echo "Can vote (Authenticated): " . ($canVote ? "YES" : "NO") . "\n";
echo "Can view_content (Authenticated): " . ($canView ? "YES" : "NO") . "\n";

if (SPPAuth::check() && $canVote && $canView) {
    echo "PASS: Authenticated user rights resolved correctly.\n";
} else {
    echo "FAIL: Authenticated user rights resolution error.\n";
}

// 4. Mock Administrator
echo "\n--- Mocking Administrator ---\n";
// Since our resolvePermissions looks for groups, we should add user 101 to Administrator group
// or just mock the administrator group for the user.
// For the test, I'll create a dummy administrator group and add the user to it.
try {
    $adminGroup = new \SPPMod\SPPAuth\SPPGroup();
    $adminGroup->load('administrator');
    $adminGroup->addMember($user);
    echo "Added user to Administrator group.\n";

    $propertyP->setValue($guard, []); // Clear cached permissions again

    $canEverything = SPPAuth::can('something_random');
    $canPublish = SPPAuth::can('publish_document');

    echo "Can something_random (Admin): " . ($canEverything ? "YES" : "NO") . "\n";
    echo "Can publish_document (Admin): " . ($canPublish ? "YES" : "NO") . "\n";

    if ($canEverything && $canPublish) {
        echo "PASS: Administrator rights (wildcard) resolved correctly.\n";
    } else {
        echo "FAIL: Administrator rights resolution error.\n";
    }

    // Cleanup
    $adminGroup->removeMember($user);
} catch (\Exception $e) {
    echo "ERROR during admin test: " . $e->getMessage() . "\n";
}

// Cleanup Session
\SPP\SPPSession::unsetSessionVar('__sppauth_user__');
echo "\n--- Verification Complete ---\n";
