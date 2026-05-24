<?php

/**
 * Global Drupal class polyfill.
 */
class Drupal {

    /**
     * @var \Lekhak\Modules\LekhakDrupalBridge\Core\DependencyInjection\Container
     */
    protected static $container;

    public static function setContainer($container) {
        static::$container = $container;
    }

    public static function getContainer() {
        return static::$container;
    }

    public static function service($id) {
        return static::$container->get($id);
    }

    public static function config($name) {
        return static::$container->get('config.factory')->get($name);
    }

    public static function database() {
        return static::$container->get('database');
    }

    public static function logger($channel) {
        return static::$container->get('logger.factory')->get($channel);
    }

    public static function currentUser() {
        return static::$container->get('current_user');
    }

    public static function state() {
        return static::$container->get('state');
    }

    public static function moduleHandler() {
        return static::$container->get('module_handler');
    }

    public static function entityTypeManager() {
        return static::$container->get('entity_type.manager');
    }

    public static function time() {
        return static::$container->get('datetime.time');
    }

    public static function formBuilder() {
        return static::$container->get('form_builder');
    }
}
