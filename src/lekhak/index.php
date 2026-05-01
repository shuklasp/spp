<?php
/**
 * Lekhak CMS Application Entry Point
 * delegates to LekhakApp for premium routing and management.
 */

require_once('../../sppinit.php');

// Ensure we are using the LekhakApp class
$app = new \App\Lekhak\LekhakApp('lekhak');
$app->handle(null);
