<?php

namespace AppMod\School1\Testmod;

/**
 * TestmodServiceProvider
 * 
 * Register your module's classes into the SPP Dependency Injection Container.
 */
class TestmodServiceProvider
{
    public function register(\SPP\Core\Container $container): void
    {
        // $container->singleton(MyService::class, fn() => new MyService());
    }
}