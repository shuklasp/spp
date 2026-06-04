<?php

namespace SPP\Core\Interfaces;

interface ModuleInterface
{
    /**
     * Get the root path of the module.
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * Include internal module files or trigger autoloading/providers.
     *
     * @return void
     */
    public function includeFiles(): void;

    /**
     * Register the module in the internal SPP Registry.
     *
     * @return void
     */
    public function register(): void;
}
