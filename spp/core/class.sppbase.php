<?php

namespace SPP;

/*require_once 'class.sppsession.php';
require_once 'sppsystemexceptions.php';
require_once 'sppconstants.php';*/
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * class \SPP\SPPBase
 *
 * @author Satya Prakash Shukla
 */
class SPPBase extends \SPP\SPPObject
{
    public static function initSession()
    {
        if (!SPPSession::sessionExists()) {
            $ssn = new SPPSession();
            $_SESSION['sppsession'] = serialize($ssn);
        }
    }

    public static function killSession()
    {
        if (SPPSession::sessionExists()) {
            unset($_SESSION['sppsession']);
            session_destroy();
        }
    }

    public static function sppTable($tname)
    {
        return \SPP\DB::sppTable($tname);
    }

    /**
     * Access the framework's resilient cache layer.
     * @return \SPP\Core\CacheInterface
     */
    public function cache()
    {
        return \SPP\Cache::driver();
    }
}
