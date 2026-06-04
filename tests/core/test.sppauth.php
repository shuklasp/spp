<?php
namespace SPP\Tests\Core;

use SPPMod\Parikshak\TestCase;
use SPPMod\SPPAuth\SPPAuth;
use SPPMod\SPPAuth\WebGuard;

class SPPAuthTest extends TestCase
{
    public function setUp(): void
    {
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
        
        $loginResult = SPPAuth::login('testuser', 'password');
        
        // Due to simple legacy proxy returning the object or boolean true
        // The mock might return void or something truthy depending on implementation
        
        $this->assertTrue(SPPAuth::check(), 'Auth check should be true after login');
        $this->assertTrue(SPPAuth::authSessionExists(), 'Legacy authSessionExists should be true after login');
    }

    public function testLogout()
    {
        SPPAuth::login('user1', 'pass');
        $this->assertTrue(SPPAuth::check());
        
        SPPAuth::logout();
        
        $this->assertFalse(SPPAuth::check(), 'Auth check should be false after logout');
    }
    
    public function testGetCurrentUser()
    {
        SPPAuth::login('adminuser', 'pass');
        
        $user = SPPAuth::user();
        $this->assertTrue($user !== null, 'User object should not be null after login');
        
        $userData = SPPAuth::getCurrentUser();
        $this->assertTrue(is_array($userData), 'getCurrentUser should return an array');
        $this->assertEquals('adminuser', $userData['id'], 'User ID should match the logged in user');
    }
}
