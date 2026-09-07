<?php
namespace SPPMod\Parikshak;

use Symfony\Component\Panther\Client;

/**
 * Trait InteractsWithBrowser
 * Provides E2E WebDriver capabilities using Symfony Panther.
 */
trait InteractsWithBrowser
{
    /**
     * @var Client|null
     */
    protected ?Client $browserClient = null;

    /**
     * Initialize or get the browser client (Headless Chrome by default)
     */
    protected function browser(): Client
    {
        if (!class_exists(Client::class)) {
            throw new \Exception("Symfony Panther is not installed. Run `composer require --dev symfony/panther`.");
        }

        if ($this->browserClient === null) {
            $this->browserClient = Client::createChromeClient();
        }

        return $this->browserClient;
    }

    /**
     * Teardown the browser client if initialized
     */
    protected function closeBrowser(): void
    {
        if ($this->browserClient !== null) {
            $this->browserClient->quit();
            $this->browserClient = null;
        }
    }
}
