<?php

namespace SPPMod\SPPView;

use SPP\SPPGlobal;
use SPP\Exceptions\AjaxRoutineNotFoundException;
use SPP\Exceptions\AjaxVariableNotFoundException;



/**
 * class Ajax
 * extends \SPP\SPPObject
 * Deals with ajax calls
 *
 * SPP Services are defined in SPP_DIR/services.php
 */
class Ajax extends \SPP\SPPObject
{
    public function __construct()
    {

    }

    /**
     * function callService()
     * Calls a service defined in services.php
     * to be executed by server script.
     */
    public static function callService()
    {
        $serv = $_REQUEST['service'];
        $services = SPPGlobal::get('services');      // Gets the list of pages for services defined
        $servdir = SPPGlobal::get('servdir');        // Gets directory of server scripts of services.
        
        if (!isset($services[$serv])) {
            throw new \SPP\SPPException('Service not registered.');
        }
        
        $path = realpath($servdir . $services[$serv]);
        $baseDir = realpath($servdir);
        
        if ($path === false || ($baseDir !== false && strpos($path, $baseDir) !== 0)) {
            throw new \SPP\SPPException('Invalid service path.');
        }
        
        require_once($path);    // Call the page of service to be called.
    }

    /**
     * function getPageLocation($page)
    public static function getPageLocation($page)
    {
        $pageData = \SPPMod\SPPRouter\SPPRouter::getPage($page);
        $pagedir = \SPP\SPPGlobal::get('pagedir');
        if (!$pageData || !isset($pageData['url'])) {
            return('');
        }
        return($pagedir.$pageData['url']);
    }

    /**
     * function loadPageContent()
     * Loads the content of the page.
     */
    public static function loadPageComponent()
    {
        $page = $_REQUEST['component'];
        $path = self::getPageLocation($page);
        
        $pagedir = realpath(SPPGlobal::get('pagedir'));
        $realPath = realpath($path);
        
        if ($realPath === false || ($pagedir !== false && strpos($realPath, $pagedir) !== 0)) {
            throw new \SPP\SPPException('Invalid component path.');
        }
        
        require($realPath);
    }

    /**
     * function isServiceRequest()
     * Returns true if the call is actually a service request and not a direct call
     */
    public static function isServiceRequest()
    {
        if (array_key_exists('service', $_REQUEST)) {
            return true;
        } else {
            return false;
        }
    }

    public static function isComponentRequest()
    {
        if (array_key_exists('component', $_REQUEST)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * function callRoutine
     * Calls the Ajax routine supplied by calling script.
     *
     * @return void
     */
    public static function callRoutine()
    {
        $rout = $_REQUEST['rout'];
        if ((str_starts_with($rout, 'ajax_') || str_starts_with($rout, 'spp_ajax_')) && function_exists($rout)) {
            call_user_func($rout);
        } else {
            throw new AjaxRoutineNotFoundException('Ajax routine '.$rout.' not found or not allowed (must start with ajax_ or spp_ajax_).');
        }
    }

    /**
     * function existsVar
     * returns true if supplied variable has been supplied by ajax call.
     *
     * @param string $var
     * Variable to be checked
     * @return boolean
     */
    public static function existsVar($var): bool
    {
        if (array_key_exists("$var", $_REQUEST)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * function getValue
     * gets a REQUEST parameter suppied to Ajax call.
     *
     * @param string $var
     * Ajax variable for which value is required.
     *
     * @return string
     * Value of the variable
     */
    public static function getValue($var)
    {
        if (array_key_exists($var, $_REQUEST)) {
            return $_REQUEST["$var"];
        } else {
            throw new AjaxVariableNotFoundException('Ajax variable '.$var.' not found.');
        }
    }

    /**
     * function returnAjax
     * Returns result of ajax call in json format
     *
     * @param array $arr
     * Result in array format
     * @return void
     */
    public static function returnAjax($arr)
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        print(json_encode($arr));
    }

    public static function getScript($path, $print = false)
    {
        ob_start();

        $realPath = realpath($path);
        if ($realPath === false || strpos($path, '../') !== false || strpos($path, '..\\') !== false) {
            ob_end_clean();
            return false;
        }

        if (is_readable($realPath)) {
            include $realPath;
        } else {
            ob_end_clean();
            return false;
        }

        if ($print == false) {
            return ob_get_clean();
        } else {
            echo ob_get_clean();
        }
    }
}
