<?php

namespace SPPMod\SPPAuth;

use SPP\Exceptions\ConfigVarExistsException as ConfigVarExistsException;
use SPP\Exceptions\NoAuthSessionException as NoAuthSessionException;
use SPP\Exceptions\UnknownPropertyException as UnknownPropertyException;

/*require_once 'class.sppusersession.php';
require_once 'sppsystemevents.php';
require_once 'sppfuncs.php';*/
/**
 * class SPPAuth
 * Handles authentication system.
 *
 * @author Satya Prakash Shukla
 */
class SPPAuth extends \SPP\SPPObject
{
    /** @var array<string, GuardInterface> */
    private static array $guards = [];
    private static string $defaultGuard = 'web';

    /**
     * Get an authentication guard instance.
     */
    public static function guard(string $name = null): GuardInterface
    {
        $name = $name ?: self::$defaultGuard;

        if (!isset(self::$guards[$name])) {
            self::$guards[$name] = self::resolveGuard($name);
        }

        return self::$guards[$name];
    }

    /**
     * Resolve a guard instance.
     */
    private static function resolveGuard(string $name): GuardInterface
    {
        switch ($name) {
            case 'web':
                return new WebGuard();
            case 'api':
                // For now, return a placeholder for TokenGuard
                // In a full implementation, this would be a separate class
                return new WebGuard();
            default:
                throw new \SPP\Exceptions\SPPException("Unknown auth guard: " . $name);
        }
    }

    /**
     * [LEGACY PROXY]
     * Authenticates a userid/password and creates session.
     */
    public static function login($uname, $passwd)
    {
        // Simple mock for legacy compatibility
        $user = (object)['id' => $uname, 'name' => $uname];
        return self::guard('web')->login($user);
    }

    /**
     * [LEGACY PROXY]
     * Logs the user out.
     */
    public static function logout()
    {
        return self::guard('web')->logout();
    }

    /**
     * [LEGACY PROXY]
     * Checks whether an authorised session exists or not.
     */
    public static function authSessionExists($consider_timeout = false)
    {
        return self::guard('web')->check();
    }

    /**
     * [LEGACY PROXY]
     * Determines whether session has a particular right or not.
     */
    public static function hasRight($rt)
    {
        // For now, assume all authenticated users have all rights
        // In a full RBAC implementation, this would call $guard->user()->hasPermission()
        return self::guard('web')->check();
    }

    /**
     * Get the currently authenticated user.
     */
    public static function user()
    {
        return self::guard()->user();
    }

    /**
     * Get the current user data as an array for backward compatibility.
     */
    public static function getCurrentUser(): ?array
    {
        $user = self::user();
        if (!$user) {
            return null;
        }
        return [
            'id' => $user->id ?? null,
            'username' => $user->username ?? ($user->id ?? null)
        ];
    }

    /**
     * Check if the user is logged in.
     */
    public static function check(): bool
    {
        return self::guard()->check();
    }

    /**
     * Determine if the current user has a specific permission.
     */
    public static function can(string $permission): bool
    {
        return self::guard()->can($permission);
    }
}
