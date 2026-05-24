<?php

/**
 * Procedural polyfills for Drupal functions.
 */

if (!function_exists('t')) {
    function t($string, array $args = [], array $options = []) {
        if (empty($args)) {
            return $string;
        }
        return strtr($string, $args);
    }
}

if (!function_exists('check_plain')) {
    function check_plain($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('watchdog_exception')) {
    function watchdog_exception($type, Exception $exception, $message = null, $variables = [], $severity = 3, $link = null) {
        error_log("[Drupal Bridge] Exception in $type: " . $exception->getMessage());
    }
}

if (!function_exists('module_invoke_all')) {
    function module_invoke_all($hook, ...$args) {
        if (class_exists('\SPP\EventKernel')) {
            \SPP\EventKernel::trigger("drupal_hook_{$hook}", $args);
        }
    }
}

if (!function_exists('variable_get')) {
    function variable_get($name, $default = null) {
        return \SPP\SPPConfig::get("drupal_var:{$name}", $default);
    }
}

if (!function_exists('variable_set')) {
    function variable_set($name, $value) {
        \SPP\SPPConfig::set("drupal_var:{$name}", $value);
    }
}

if (!function_exists('db_query')) {
    function db_query($query, array $args = [], array $options = []) {
        return \Drupal::database()->query($query, $args, $options);
    }
}

if (!function_exists('db_select')) {
    function db_select($table, $alias = null, array $options = []) {
        return \Drupal::database()->select($table, $alias, $options);
    }
}

if (!function_exists('db_insert')) {
    function db_insert($table, array $options = []) {
        return \Drupal::database()->insert($table, $options);
    }
}

if (!function_exists('db_update')) {
    function db_update($table, array $options = []) {
        return \Drupal::database()->update($table, $options);
    }
}

if (!function_exists('db_delete')) {
    function db_delete($table, array $options = []) {
        return \Drupal::database()->delete($table, $options);
    }
}

if (!function_exists('url')) {
    function url($path = null, array $options = []) {
        $base = \SPP\App::getBaseUrl();
        if ($path === '<front>' || empty($path)) return $base . '/';
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('l')) {
    function l($text, $path, array $options = []) {
        return '<a href="' . htmlspecialchars(url($path, $options), ENT_QUOTES, 'UTF-8') . '">' . check_plain($text) . '</a>';
    }
}

if (!function_exists('drupal_get_path')) {
    function drupal_get_path($type, $name) {
        if ($type === 'module') {
            if (class_exists('\SPPMod\Lekhak\Core\ModuleRegistry')) {
                $mods = \SPPMod\Lekhak\Core\ModuleRegistry::getModules();
                if (isset($mods[$name]['path'])) {
                    return $mods[$name]['path'];
                }
            }
        }
        return '';
    }
}

if (!function_exists('theme')) {
    function theme($hook, $variables = []) {
        if (class_exists('\SPP\EventKernel')) {
            $res = \SPP\EventKernel::trigger("drupal_theme_{$hook}", $variables);
            if (!empty($res)) return implode('', $res);
        }
        return '';
    }
}