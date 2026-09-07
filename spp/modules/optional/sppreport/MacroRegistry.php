<?php
/**
 * Class MacroRegistry
 * Resolves dynamic placeholders inside SPPReport configurations.
 */

namespace SPPMod\SPPReport;

class MacroRegistry
{
    private static array $macros = [];

    /**
     * Registers a custom dynamic macro.
     */
    public static function register(string $name, callable $resolver): void
    {
        self::$macros[$name] = $resolver;
    }

    /**
     * Resolves a placeholder like {{TODAY}} into its dynamic value.
     */
    public static function resolve(string $placeholder)
    {
        // Strip {{ and }}
        $key = trim(str_replace(['{{', '}}'], '', $placeholder));
        
        if (isset(self::$macros[$key])) {
            return call_user_func(self::$macros[$key]);
        }

        // Built-ins
        if ($key === 'CURRENT_USER_ID') {
            if (class_exists('\\SPPMod\\SPPAuth\\SPPAuth')) {
                $user = \SPPMod\SPPAuth\SPPAuth::getCurrentUser();
                return $user['id'] ?? 0;
            }
            return 0;
        }

        if ($key === 'CURRENT_ROLE_ID') {
            if (class_exists('\\SPPMod\\SPPAuth\\SPPAuth')) {
                $user = \SPPMod\SPPAuth\SPPAuth::user();
                if ($user && method_exists($user, 'getRoles')) {
                    $roles = $user->getRoles();
                    return !empty($roles) ? $roles[0] : 0;
                }
            }
            return 0;
        }

        if ($key === 'TODAY') {
            return date('Y-m-d');
        }

        if ($key === 'YESTERDAY') {
            return date('Y-m-d', strtotime('-1 day'));
        }

        if ($key === 'LAST_30_DAYS') {
            return date('Y-m-d', strtotime('-30 days'));
        }

        if ($key === 'LAST_7_DAYS') {
            return date('Y-m-d', strtotime('-7 days'));
        }

        if ($key === 'THIS_MONTH_START') {
            return date('Y-m-01');
        }

        if ($key === 'THIS_YEAR_START') {
            return date('Y-01-01');
        }

        // Unresolved placeholder fallback
        return null;
    }
}
