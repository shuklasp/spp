<?php

namespace AppMod\School1\Testappmod;

/**
 * TestappmodServiceProvider
 * 
 * Register your module's classes into the SPP Dependency Injection Container.
 */
class TestappmodServiceProvider
{
    public function register(\SPP\Core\Container $container): void
    {
        // $container->singleton(MyService::class, fn() => new MyService());
    }
}