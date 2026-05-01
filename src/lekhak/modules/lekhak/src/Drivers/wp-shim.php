<?php
/**
 * Lekhak WP-Lite Shim
 * Defines standard WordPress functions for template compatibility.
 */

if (!function_exists('the_title')) {
    function the_title($before = '', $after = '', $echo = true) {
        $node = \SPPMod\Lekhak\Drivers\WPSimDriver::getContext('node');
        $title = $node ? $node->title : (\SPPMod\Lekhak\Drivers\WPSimDriver::getContext('title') ?? '');
        $out = $before . $title . $after;
        if ($echo) echo $out;
        return $out;
    }
}

if (!function_exists('the_content')) {
    function the_content() {
        $node = \SPPMod\Lekhak\Drivers\WPSimDriver::getContext('node');
        if ($node) {
            echo $node->body;
        }
    }
}

if (!function_exists('have_posts')) {
    function have_posts() {
        $posts = \SPPMod\Lekhak\Drivers\WPSimDriver::getContext('posts');
        return !empty($posts);
    }
}

if (!function_exists('the_post')) {
    function the_post() {
        // Simplified loop management
    }
}

if (!function_exists('get_header')) {
    function get_header($name = null) {
        // Look for header.php in current theme
    }
}

if (!function_exists('get_footer')) {
    function get_footer($name = null) {
        // Look for footer.php in current theme
    }
}

if (!function_exists('add_action')) {
    function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
        \SPP\SPPEvent::registerHandler($tag, $function_to_add);
    }
}

if (!function_exists('do_action')) {
    function do_action($tag, ...$arg) {
        \SPP\SPPEvent::fireEvent($tag, $arg);
    }
}
