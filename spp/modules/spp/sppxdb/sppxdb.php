<?php
/**
 * SPP XDB Module Bridge
 * 
 * Provides initialization and helper functions for the SPP XML Database module.
 */

require_once(__DIR__ . '/class.sppxdb.php');
require_once(__DIR__ . '/class.querybuilder.php');
require_once(__DIR__ . '/class.xdbcontroller.php');
require_once(__DIR__ . '/class.migrationmanager.php');

/**
 * Global helper to get an instance of the XML Database.
 * 
 * @param string $db Database name (defaults to 'default').
 * @param string|null $table Optional table name to initialize with.
 * @return \SPPMod\SPPXDB\SPP_XDB
 */
function get_xdb($db = 'default', $table = null) {
    return new \SPPMod\SPPXDB\SPP_XDB($db, $table);
}

// Register XDB Triggers (SPP Events)
if (class_exists('\\SPP\\SPPEvent')) {
    \SPP\SPPEvent::registerEvent('xdb.before_insert');
    \SPP\SPPEvent::registerEvent('xdb.after_insert');
    \SPP\SPPEvent::registerEvent('xdb.before_update');
    \SPP\SPPEvent::registerEvent('xdb.after_update');
    \SPP\SPPEvent::registerEvent('xdb.before_delete');
    \SPP\SPPEvent::registerEvent('xdb.after_delete');
}
