<?php

namespace SPP;

use SPP\Exceptions;
use SPP\Exceptions\SessionDoesNotExistException;
use SPP\Exceptions\UnknownSessionVarException;

/*require_once 'class.sppobject.php';
require_once 'sppsystemexceptions.php';
require_once 'class.spperror.php';*/
/**
 * class SPPSession
 * Handles session variables in SPP
 *
 * @author Satya Prakash Shukla
 */

class SPPSession extends \SPP\SPPObject
{
    private static ?\SPP\Core\Interfaces\SPPSessionBridgeInterface $bridge = null;

    public static function setBridge(\SPP\Core\Interfaces\SPPSessionBridgeInterface $bridge): void
    {
        self::$bridge = $bridge;
    }

    private static function fetchSession(): SPPSession
    {
        $ssname = \SPP\App::getSessionName();
        if (!isset($_SESSION[$ssname])) {
            if (session_status() === PHP_SESSION_ACTIVE || php_sapi_name() === 'cli') {
                $_SESSION[$ssname] = ['vars' => []];
            } else {
                throw new SessionDoesNotExistException('No session exists! (Bucket ' . $ssname . ' not found in $_SESSION)');
            }
        } elseif (is_string($_SESSION[$ssname])) {
            // Migration: convert old serialized object to array
            $oldObj = unserialize($_SESSION[$ssname], ['allowed_classes' => ['SPP\SPPSession']]);
            $vars = [];
            if ($oldObj instanceof SPPSession && property_exists($oldObj, 'sessvars')) {
                $ref = new \ReflectionProperty(get_class($oldObj), 'sessvars');
                $ref->setAccessible(true);
                $vars = $ref->getValue($oldObj);
            }
            $_SESSION[$ssname] = ['vars' => $vars];
        }

        return new self();
    }

    private static function saveSession(): void
    {
        if (self::$bridge !== null) {
            $ssname = \SPP\App::getSessionName();
            if (isset($_SESSION[$ssname]['vars'])) {
                self::$bridge->sync(session_id(), $_SESSION[$ssname]['vars']);
            }
        }
    }

    public static function destroySession(): void
    {
        if (self::$bridge !== null) {
            self::$bridge->destroy(session_id());
        }
        
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function regenerateId(bool $deleteOldSession = false): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return @session_regenerate_id($deleteOldSession);
        }
        return false;
    }

    /**
     * private function startSession()
     * Start a session if it does not already exist.
     */
    public function __construct()
    {
        // Initialization handled in fetchSession
    }

    /**
     * public function sessionExists()
     * Checks whether a SPP session exists or not.
     *
     * @return bool
     */
    public static function sessionExists()
    {
        $ssname = \SPP\App::getSessionName();
        if (isset($_SESSION) && array_key_exists($ssname, $_SESSION)) {
            return true;
        } else {
            return false;
        }
    }



    /**
     * static function validSessionVarExists()
     * Checks whether a variable exists or not.
     *
     * @param string $varname
     * @return bool
     */
    public static function validSessionVarExists($varname)
    {
        return self::fetchSession()->validVarExists($varname);
    }



    /**
     * static function sessionVarExists()
     * Checks whether a variable exists or not.
     *
     * @param string $varname
     * @return bool
     */
    public static function sessionVarExists($varname)
    {
        return self::fetchSession()->varExists($varname);
    }


    /**
     * static function getSessionVar()
     * Gets a session variable.
     *
     * @param string $varname
     * @return mixed
     */
    public static function getSessionVar($varname)
    {
        return self::fetchSession()->getVar($varname);
    }

    /**
     * static function setSessionVar()
     * Sets a custom session variable.
     *
     * @param string $varname
     * @param mixed $varval
     */
    public static function setSessionVar($varname, $varval, $bridged = false)
    {
        $ssn = self::fetchSession();
        $ssn->setVar($varname, $varval, $bridged);
        self::saveSession();
    }

    /**
     * static function unsetSessionVar()
     * Unsets a custom session variable.
     *
     * @param string $varname
     */
    public static function unsetSessionVar($varname)
    {
        $ssn = self::fetchSession();
        $ssn->unsetVar($varname);
        self::saveSession();
    }


    /**
     * static function invalidateSessionVar()
     * Invalidates a custom session variable.
     *
     * @param string $varname
     */
    public static function invalidateSessionVar($varname)
    {
        $ssn = self::fetchSession();
        $ssn->invalidateVar($varname);
        self::saveSession();
    }


    /**
     * function setVar()
     * Sets the value of an application defined session variable.
     *
     * @param string $varname
     * @param mixed $varval
     */
    public function setVar($varname, $varval, $bridged = false)
    {
        $ssname = \SPP\App::getSessionName();
        $_SESSION[$ssname]['vars'][$varname] = [
            'val' => $varval,
            'isactive' => true,
            'bridged' => $bridged
        ];
    }

    /**
     * function unsetVar()
     * Unsets a spp session variable.
     *
     * @param mixed $varname
     */
    public function unsetVar($varname)
    {
        $ssname = \SPP\App::getSessionName();
        unset($_SESSION[$ssname]['vars'][$varname]);
    }

    /**
     * function varExists()
     * Returns true if session variable exists.
     *
     * @param <type> $varname
     * @return <type>
     */
    public function varExists($varname)
    {
        $ssname = \SPP\App::getSessionName();
        return isset($_SESSION[$ssname]['vars']) && array_key_exists($varname, $_SESSION[$ssname]['vars']);
    }

    /**
     * function validVarExists()
     * Finds if a particular valid variable exists or not.
     *
     * @param string $varname
     * @return bool
     */
    public function validVarExists($varname)
    {
        $ssname = \SPP\App::getSessionName();
        if (isset($_SESSION[$ssname]['vars']) && array_key_exists($varname, $_SESSION[$ssname]['vars'])) {
            return !empty($_SESSION[$ssname]['vars'][$varname]['isactive']);
        }
        return false;
    }

    /**
     * function getVar()
     * Gets the value of a custom variable.
     *
     * @param <type> $varname
     * @return <type>
     */
    public function getVar($varname)
    {
        if ($this->validVarExists($varname)) {
            $ssname = \SPP\App::getSessionName();
            return $_SESSION[$ssname]['vars'][$varname]['val'];
        } else {
            throw new UnknownSessionVarException('Undefined session variable ' . $varname . ' accessed.');
        }
    }

    /**
     * function invalidateVar()
     * Invalidate a session registered variable.
     *
     * @param <type> $varname
     */
    public function invalidateVar($varname)
    {
        if ($this->validVarExists($varname)) {
            $ssname = \SPP\App::getSessionName();
            $_SESSION[$ssname]['vars'][$varname]['isactive'] = false;
        } else {
            throw new UnknownSessionVarException('Undefined session variable ' . $varname . ' accessed.');
        }
    }

    public static function getCsrfToken(): string
    {
        try {
            return self::getSessionVar('__csrf_token__');
        } catch (UnknownSessionVarException $e) {
            return self::generateCsrfToken();
        }
    }

    public static function generateCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        self::setSessionVar('__csrf_token__', $token);
        return $token;
    }

    public static function setFlash(string $key, $value): void
    {
        $flashes = self::validSessionVarExists('__flashes') ? self::getSessionVar('__flashes') : [];
        $flashes[$key] = $value;
        self::setSessionVar('__flashes', $flashes);
    }

    public static function hasFlash(string $key): bool
    {
        if (!self::validSessionVarExists('__flashes')) return false;
        $flashes = self::getSessionVar('__flashes');
        return isset($flashes[$key]);
    }

    public static function getFlash(string $key)
    {
        if (!self::validSessionVarExists('__flashes')) return null;
        $flashes = self::getSessionVar('__flashes');
        if (isset($flashes[$key])) {
            $val = $flashes[$key];
            unset($flashes[$key]);
            self::setSessionVar('__flashes', $flashes);
            return $val;
        }
        return null;
    }


}
