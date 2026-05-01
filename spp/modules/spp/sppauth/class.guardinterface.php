<?php
namespace SPPMod\SPPAuth;

/**
 * Interface GuardInterface
 * Defines the contract for an authentication driver.
 */
interface GuardInterface {
    public function check(): bool;
    public function user();
    public function id();
    public function can(string $permission): bool;
    public function login($user, bool $remember = false);
    public function logout();
}
