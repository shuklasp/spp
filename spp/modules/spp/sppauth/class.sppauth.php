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
                return new TokenGuard();
            default:
                throw new \SPP\SPPException("Unknown auth guard: " . $name);
        }
    }

    /**
     * [LEGACY PROXY]
     * Authenticates a userid/password and creates session.
     */
    public static function login($uname, $passwd)
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (RateLimiter::tooManyAttempts($uname, $ip)) {
            AuditLogger::log('login_failed_bruteforce', null, $uname, "IP: $ip");
            throw new \Exception("Too many login attempts. Please try again later.");
        }

        try {
            if (SPPUser::verifyUserPassword($uname, $passwd)) {
                RateLimiter::clear($uname, $ip);
                $user = new SPPUser($uname);

                // Intercept for MFA
                $mfaEnabled = false;
                try {
                    $mfaEnabled = $user->get('mfa_enabled');
                } catch (\Exception $e) {
                }

                if ($mfaEnabled) {
                    $token = bin2hex(random_bytes(16));
                    \SPP\SPPSession::setSessionVar('mfa_challenge_user', $user->id);
                    \SPP\SPPSession::setSessionVar('mfa_challenge_token', $token);
                    throw new \Exception("MFA_REQUIRED:$token");
                }

                self::guard('web')->login($user);
                AuditLogger::log('login_success', $user->id, null, "IP: $ip via SPPAuth::login");
                return true;
            }
        } catch (\Exception $e) {
            if (str_starts_with($e->getMessage(), 'MFA_REQUIRED:')) {
                throw $e; // Bubble up MFA challenges
            }
        }

        RateLimiter::hit($uname, $ip);
        AuditLogger::log('login_failed', null, $uname, "IP: $ip");
        return false;
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
