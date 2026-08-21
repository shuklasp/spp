<?php
/**
 * XDB Management Service Group for SPP Admin
 */

require_once SPP_BASE_DIR . '/modules/spp/sppxdb/class.sppxdb.php';
require_once SPP_BASE_DIR . '/modules/spp/sppxdb/class.xdbmigrator.php';
require_once SPP_BASE_DIR . '/modules/spp/sppxdb/class.seedermanager.php';

function live_XDB_ListDB($la, $params) {
    $res = \SPP\CLI\CommandManager::execute('dev:xdb', ['listdb', '--payload' => json_encode($params), '--json' => '1']);
    if ($res['success']) {
        $data = json_decode($res['output'], true);
        if (isset($data['success']) && !$data['success']) {
            $la->setStatus('error')->notify($data['error'] ?? 'Command failed.');
        } elseif (isset($data['modal'])) {
            $la->modal($data['modal']['title'], $data['modal']['html'], $data['modal']['buttons'] ?? []);
        } elseif (isset($data['message'])) {
            $la->notify($data['message']);
            if (!empty($data['closeModal'])) $la->closeModal();
            if (!empty($data['refresh'])) $la->refresh();
            if (!empty($data['executeClientCode'])) $la->executeClientCode($data['executeClientCode']);
            if (!empty($data['redirect'])) $la->redirect($data['redirect']);
        } else {
            $la->setData($data ?: []);
        }
    } else {
        $la->setStatus('error')->notify($res['error']);
    }
}

function live_XDB_ListTables($la, $params) {
    $res = \SPP\CLI\CommandManager::execute('dev:xdb', ['listtables', '--payload' => json_encode($params), '--json' => '1']);
    if ($res['success']) {
        $data = json_decode($res['output'], true);
        if (isset($data['success']) && !$data['success']) {
            $la->setStatus('error')->notify($data['error'] ?? 'Command failed.');
        } elseif (isset($data['modal'])) {
            $la->modal($data['modal']['title'], $data['modal']['html'], $data['modal']['buttons'] ?? []);
        } elseif (isset($data['message'])) {
            $la->notify($data['message']);
            if (!empty($data['closeModal'])) $la->closeModal();
            if (!empty($data['refresh'])) $la->refresh();
            if (!empty($data['executeClientCode'])) $la->executeClientCode($data['executeClientCode']);
            if (!empty($data['redirect'])) $la->redirect($data['redirect']);
        } else {
            $la->setData($data ?: []);
        }
    } else {
        $la->setStatus('error')->notify($res['error']);
    }
}

function live_XDB_GetTableData($la, $params) {
    $res = \SPP\CLI\CommandManager::execute('dev:xdb', ['gettabledata', '--payload' => json_encode($params), '--json' => '1']);
    if ($res['success']) {
        $data = json_decode($res['output'], true);
        if (isset($data['success']) && !$data['success']) {
            $la->setStatus('error')->notify($data['error'] ?? 'Command failed.');
        } elseif (isset($data['modal'])) {
            $la->modal($data['modal']['title'], $data['modal']['html'], $data['modal']['buttons'] ?? []);
        } elseif (isset($data['message'])) {
            $la->notify($data['message']);
            if (!empty($data['closeModal'])) $la->closeModal();
            if (!empty($data['refresh'])) $la->refresh();
            if (!empty($data['executeClientCode'])) $la->executeClientCode($data['executeClientCode']);
            if (!empty($data['redirect'])) $la->redirect($data['redirect']);
        } else {
            $la->setData($data ?: []);
        }
    } else {
        $la->setStatus('error')->notify($res['error']);
    }
}

function live_XDB_GetTableColumns($la, $params) {
    $res = \SPP\CLI\CommandManager::execute('dev:xdb', ['gettablecolumns', '--payload' => json_encode($params), '--json' => '1']);
    if ($res['success']) {
        $data = json_decode($res['output'], true);
        if (isset($data['success']) && !$data['success']) {
            $la->setStatus('error')->notify($data['error'] ?? 'Command failed.');
        } elseif (isset($data['modal'])) {
            $la->modal($data['modal']['title'], $data['modal']['html'], $data['modal']['buttons'] ?? []);
        } elseif (isset($data['message'])) {
            $la->notify($data['message']);
            if (!empty($data['closeModal'])) $la->closeModal();
            if (!empty($data['refresh'])) $la->refresh();
            if (!empty($data['executeClientCode'])) $la->executeClientCode($data['executeClientCode']);
            if (!empty($data['redirect'])) $la->redirect($data['redirect']);
        } else {
            $la->setData($data ?: []);
        }
    } else {
        $la->setStatus('error')->notify($res['error']);
    }
}

function live_XDB_RunQuery($la, $params) {
    $res = \SPP\CLI\CommandManager::execute('dev:xdb', ['runquery', '--payload' => json_encode($params), '--json' => '1']);
    if ($res['success']) {
        $data = json_decode($res['output'], true);
        if (isset($data['success']) && !$data['success']) {
            $la->setStatus('error')->notify($data['error'] ?? 'Command failed.');
        } elseif (isset($data['modal'])) {
            $la->modal($data['modal']['title'], $data['modal']['html'], $data['modal']['buttons'] ?? []);
        } elseif (isset($data['message'])) {
            $la->notify($data['message']);
            if (!empty($data['closeModal'])) $la->closeModal();
            if (!empty($data['refresh'])) $la->refresh();
            if (!empty($data['executeClientCode'])) $la->executeClientCode($data['executeClientCode']);
            if (!empty($data['redirect'])) $la->redirect($data['redirect']);
        } else {
            $la->setData($data ?: []);
        }
    } else {
        $la->setStatus('error')->notify($res['error']);
    }
}

function live_XDB_SaveRecord($la, $params) {
    $res = \SPP\CLI\CommandManager::execute('dev:xdb', ['saverecord', '--payload' => json_encode($params), '--json' => '1']);
    if ($res['success']) {
        $data = json_decode($res['output'], true);
        if (isset($data['success']) && !$data['success']) {
            $la->setStatus('error')->notify($data['error'] ?? 'Command failed.');
        } elseif (isset($data['modal'])) {
            $la->modal($data['modal']['title'], $data['modal']['html'], $data['modal']['buttons'] ?? []);
        } elseif (isset($data['message'])) {
            $la->notify($data['message']);
            if (!empty($data['closeModal'])) $la->closeModal();
            if (!empty($data['refresh'])) $la->refresh();
            if (!empty($data['executeClientCode'])) $la->executeClientCode($data['executeClientCode']);
            if (!empty($data['redirect'])) $la->redirect($data['redirect']);
        } else {
            $la->setData($data ?: []);
        }
    } else {
        $la->setStatus('error')->notify($res['error']);
    }
}

function live_XDB_DeleteRecord($la, $params) {
    $res = \SPP\CLI\CommandManager::execute('dev:xdb', ['deleterecord', '--payload' => json_encode($params), '--json' => '1']);
    if ($res['success']) {
        $data = json_decode($res['output'], true);
        if (isset($data['success']) && !$data['success']) {
            $la->setStatus('error')->notify($data['error'] ?? 'Command failed.');
        } elseif (isset($data['modal'])) {
            $la->modal($data['modal']['title'], $data['modal']['html'], $data['modal']['buttons'] ?? []);
        } elseif (isset($data['message'])) {
            $la->notify($data['message']);
            if (!empty($data['closeModal'])) $la->closeModal();
            if (!empty($data['refresh'])) $la->refresh();
            if (!empty($data['executeClientCode'])) $la->executeClientCode($data['executeClientCode']);
            if (!empty($data['redirect'])) $la->redirect($data['redirect']);
        } else {
            $la->setData($data ?: []);
        }
    } else {
        $la->setStatus('error')->notify($res['error']);
    }
}

function live_XDB_Migrate($la, $params) {
    $res = \SPP\CLI\CommandManager::execute('dev:xdb', ['migrate', '--payload' => json_encode($params), '--json' => '1']);
    if ($res['success']) {
        $data = json_decode($res['output'], true);
        if (isset($data['success']) && !$data['success']) {
            $la->setStatus('error')->notify($data['error'] ?? 'Command failed.');
        } elseif (isset($data['modal'])) {
            $la->modal($data['modal']['title'], $data['modal']['html'], $data['modal']['buttons'] ?? []);
        } elseif (isset($data['message'])) {
            $la->notify($data['message']);
            if (!empty($data['closeModal'])) $la->closeModal();
            if (!empty($data['refresh'])) $la->refresh();
            if (!empty($data['executeClientCode'])) $la->executeClientCode($data['executeClientCode']);
            if (!empty($data['redirect'])) $la->redirect($data['redirect']);
        } else {
            $la->setData($data ?: []);
        }
    } else {
        $la->setStatus('error')->notify($res['error']);
    }
}

function live_XDB_Seed($la, $params) {
    $res = \SPP\CLI\CommandManager::execute('dev:xdb', ['seed', '--payload' => json_encode($params), '--json' => '1']);
    if ($res['success']) {
        $data = json_decode($res['output'], true);
        if (isset($data['success']) && !$data['success']) {
            $la->setStatus('error')->notify($data['error'] ?? 'Command failed.');
        } elseif (isset($data['modal'])) {
            $la->modal($data['modal']['title'], $data['modal']['html'], $data['modal']['buttons'] ?? []);
        } elseif (isset($data['message'])) {
            $la->notify($data['message']);
            if (!empty($data['closeModal'])) $la->closeModal();
            if (!empty($data['refresh'])) $la->refresh();
            if (!empty($data['executeClientCode'])) $la->executeClientCode($data['executeClientCode']);
            if (!empty($data['redirect'])) $la->redirect($data['redirect']);
        } else {
            $la->setData($data ?: []);
        }
    } else {
        $la->setStatus('error')->notify($res['error']);
    }
}

function live_XDB_GetProfileLog($la, $params) {
    $res = \SPP\CLI\CommandManager::execute('dev:xdb', ['getprofilelog', '--payload' => json_encode($params), '--json' => '1']);
    if ($res['success']) {
        $data = json_decode($res['output'], true);
        if (isset($data['success']) && !$data['success']) {
            $la->setStatus('error')->notify($data['error'] ?? 'Command failed.');
        } elseif (isset($data['modal'])) {
            $la->modal($data['modal']['title'], $data['modal']['html'], $data['modal']['buttons'] ?? []);
        } elseif (isset($data['message'])) {
            $la->notify($data['message']);
            if (!empty($data['closeModal'])) $la->closeModal();
            if (!empty($data['refresh'])) $la->refresh();
            if (!empty($data['executeClientCode'])) $la->executeClientCode($data['executeClientCode']);
            if (!empty($data['redirect'])) $la->redirect($data['redirect']);
        } else {
            $la->setData($data ?: []);
        }
    } else {
        $la->setStatus('error')->notify($res['error']);
    }
}
