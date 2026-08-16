<?php
namespace SPP\Tests\Core;

use SPPMod\Parikshak\SPPTestCase;
use SPPMod\SPPAuth\SPPAuth;
use SPPMod\SPPAuth\WebGuard;

class SPPAuthTest extends SPPTestCase
{
    public function setUp(): void
    {
        // Re-initialize DB provider for the current context (e.g. lekhak's in-memory SQLite)
        if (class_exists('\\SPP\\DB')) {
            \SPP\DB::setProvider(new \SPPMod\SPPDB\SPPDB());
        }

        $db = new \SPPMod\SPPDB\SPPDB();
        $t_users = \SPPMod\SPPDB\SPPDB::sppTable('users');
        $t_loginrec = \SPPMod\SPPDB\SPPDB::sppTable('loginrec');
        $t_remember = \SPPMod\SPPDB\SPPDB::sppTable('remember_tokens');

        $db->exec("CREATE TABLE IF NOT EXISTS $t_loginrec (sessid VARCHAR(255), uid VARCHAR(255), logintime TIMESTAMP, ipaddr VARCHAR(255), lastaccess TIMESTAMP)");
        $db->exec("CREATE TABLE IF NOT EXISTS $t_remember (user_id VARCHAR(255), token_hash VARCHAR(255), expires_at TIMESTAMP)");

        $db->execute_query("DELETE FROM $t_users");
        $db->execute_query("DELETE FROM $t_loginrec");
        $db->execute_query("DELETE FROM $t_remember");
        
        $db->execute_query("INSERT INTO $t_users (id, username, email, password_hash) VALUES (1, 'testuser', 'test@test.com', 'dummyhash')");
        $db->execute_query("INSERT INTO $t_users (id, username, email, password_hash) VALUES (2, 'user1', 'user1@test.com', 'dummyhash')");
        $db->execute_query("INSERT INTO $t_users (id, username, email, password_hash) VALUES (3, 'adminuser', 'admin@test.com', 'dummyhash')");

        // Logout first just in case tests run sequentially and state leaks
        SPPAuth::logout();
    }

    public function tearDown(): void
    {
        SPPAuth::logout();
    }

    public function testDefaultGuardIsWeb()
    {
        $guard = SPPAuth::guard();
        $this->assertInstanceOf('SPPMod\SPPAuth\WebGuard', $guard, 'Default guard should be an instance of WebGuard');
    }

    public function testLoginAndCheck()
    {
        $this->assertFalse(SPPAuth::check(), 'Auth check should be false initially');

        $loginResult = SPPAuth::guard()->login('testuser');

        // Due to simple legacy proxy returning the object or boolean true
        // The mock might return void or something truthy depending on implementation

        $this->assertTrue(SPPAuth::check(), 'Auth check should be true after login');
        $this->assertTrue(SPPAuth::authSessionExists(), 'Legacy authSessionExists should be true after login');
    }

    public function testLogout()
    {
        SPPAuth::guard()->login('user1');
        $this->assertTrue(SPPAuth::check());

        SPPAuth::logout();

        $this->assertFalse(SPPAuth::check(), 'Auth check should be false after logout');
    }

    public function testGetCurrentUser()
    {
        SPPAuth::guard()->login('adminuser');

        $user = SPPAuth::user();
        $this->assertTrue($user !== null, 'User object should not be null after login');

        $userData = SPPAuth::getCurrentUser();
        $this->assertTrue(is_array($userData), 'getCurrentUser should return an array');
        $this->assertEquals('adminuser', $userData['username'], 'User username should match the logged in user');
    }
}
