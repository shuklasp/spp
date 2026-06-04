<?php

namespace SPPMod\SPPAuth;

/**
 * Interface UserProviderInterface
 * Defines the contract for retrieving users from a persistent store.
 */
interface UserProviderInterface
{
    public function retrieveById($id);
    public function retrieveByCredentials(array $credentials);
    public function validateCredentials($user, array $credentials): bool;
}
