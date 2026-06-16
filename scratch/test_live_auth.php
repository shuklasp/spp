<?php
require_once 'spp/sppinit.php';
session_start(); // Force session_id() to exist
try { \SPP\Scheduler::getProcObj('sppadmin'); } catch (\Exception $e) { new \SPP\App('sppadmin', false, 3); }
\SPP\Scheduler::setContext('sppadmin');

require_once 'spp/admin/services/Auth.php';

class MockLA {
    public function setStatus($s) { return $this; }
    public function setData($d) { return $this; }
    public function notify($m, $type='info') {
        echo "LA Notify [$type]: $m\n";
        return true;
    }
}

$la = new MockLA();
live_Auth_Login($la, ['username' => 'admin', 'password' => 'admin123']);
