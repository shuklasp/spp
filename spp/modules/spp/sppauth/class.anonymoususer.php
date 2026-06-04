<?php

namespace SPPMod\SPPAuth;

/**
 * Class AnonymousUser
 *
 * Represents an unauthenticated user within the SPP framework.
 * This class ensures that a user object is always available,
 * fulfilling the requirement for a "virtual" anonymous user.
 */
class AnonymousUser extends \SPP\SPPObject
{
    public $id = 'anonymous';
    public $username = 'Guest';
    public $status = 'active';

    /**
     * Get user ID.
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Property getter for compatibility.
     */
    public function get($prop)
    {
        if ($prop === 'UserId') {
            return $this->id;
        }
        if ($prop === 'UserName') {
            return $this->username;
        }
        if ($prop === 'rights') {
            return [];
        }
        return $this->$prop ?? null;
    }

    /**
     * Anonymous users cannot verify passwords.
     */
    public function verifyPassword($passwd)
    {
        return false;
    }

    /**
     * Anonymous users are always enabled (as guests).
     */
    public function isEnabled()
    {
        return true;
    }
}
