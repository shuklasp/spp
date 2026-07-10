<?php
namespace SPPMod\SPPIntegrations;

/**
 * Interface ExternalAppDriverInterface
 * 
 * Defines the contract that all external application drivers must implement
 * to ensure seamless integration with SPP.
 */
interface ExternalAppDriverInterface
{
    /**
     * @param array $config Configuration options (credentials, tokens, URLs)
     */
    public function __construct(array $config);

    /**
     * Synchronize a user object to the external application.
     * 
     * @param array $userData The user data to sync
     * @return bool True on success, false on failure
     */
    public function syncUser(array $userData): bool;

    /**
     * Fetch generic data from the external application's endpoint.
     * 
     * @param string $endpoint The endpoint or query to fetch
     * @return array The decoded JSON response or data array
     */
    public function fetchData(string $endpoint): array;

    /**
     * Push a domain event (webhook-style) to the external application.
     * 
     * @param string $eventName The name of the event
     * @param array $payload The event payload
     * @return bool True on success, false on failure
     */
    public function pushEvent(string $eventName, array $payload): bool;

    /**
     * Perform Single Sign-On (SSO) login for a user by setting the external application's session cookies.
     * 
     * @param array $userData The user data containing identifier/credentials
     * @return bool True on success, false on failure
     */
    public function loginUser(array $userData): bool;
}
