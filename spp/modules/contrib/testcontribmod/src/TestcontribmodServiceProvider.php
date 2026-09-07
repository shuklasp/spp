<?php

namespace ContribMod\Testcontribmod;

/**
 * TestcontribmodServiceProvider
 * 
 * Register your module's classes into the SPP Dependency Injection Container.
 */
class TestcontribmodServiceProvider
{
    public function register(\SPP\Core\Container $container): void
    {
        // $container->singleton(MyService::class, fn() => new MyService());
    }
}