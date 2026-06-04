<?php

namespace SPP\Core\Interfaces;

interface ServiceProviderInterface
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void;

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void;

    /**
     * Optional update hook triggered when the module is being updated
     * from one version to another.
     * 
     * @param string $fromVersion The old version
     * @param string $toVersion The new version
     */
    // public function update(string $fromVersion, string $toVersion): void;

    /**
     * Optional scheduler hook triggered by `spp schedule:run` command
     * to register module-specific background cron jobs.
     * 
     * @param \SPP\Cron\Scheduler $schedule The central scheduling registry
     */
    // public function schedule(\SPP\Cron\Scheduler $schedule): void;
}
