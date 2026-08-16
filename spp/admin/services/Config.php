<?php
/**
 * Configuration Service Group for SPP Admin
 */

function live_Config_InterDB_Get($la, $params)
{
        $res = \SPP\CLI\CommandManager::execute('admin:config', ['interdb_get', '--payload' => json_encode($params), '--json' => '1']);
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
            } else {
                $la->setData($data ?: []);
            }
        } else {
            $la->setStatus('error')->notify($res['error']);
        }

}

function live_Config_InterDB_Save($la, $params)
{
        $res = \SPP\CLI\CommandManager::execute('admin:config', ['interdb_save', '--payload' => json_encode($params), '--json' => '1']);
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
            } else {
                $la->setData($data ?: []);
            }
        } else {
            $la->setStatus('error')->notify($res['error']);
        }

}

function live_Config_Ajax_List($la, $params)
{
        $res = \SPP\CLI\CommandManager::execute('admin:config', ['ajax_list', '--payload' => json_encode($params), '--json' => '1']);
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
            } else {
                $la->setData($data ?: []);
            }
        } else {
            $la->setStatus('error')->notify($res['error']);
        }

}

function live_Config_Ajax_Save($la, $params)
{
        $res = \SPP\CLI\CommandManager::execute('admin:config', ['ajax_save', '--payload' => json_encode($params), '--json' => '1']);
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
            } else {
                $la->setData($data ?: []);
            }
        } else {
            $la->setStatus('error')->notify($res['error']);
        }

}
function live_Config_GetGlobalSettings($la, $params)
{
        $res = \SPP\CLI\CommandManager::execute('admin:config', ['getglobalsettings', '--payload' => json_encode($params), '--json' => '1']);
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
            } else {
                $la->setData($data ?: []);
            }
        } else {
            $la->setStatus('error')->notify($res['error']);
        }

}

function live_Config_SaveGlobalSettings($la, $params)
{
        $res = \SPP\CLI\CommandManager::execute('admin:config', ['saveglobalsettings', '--payload' => json_encode($params), '--json' => '1']);
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
            } else {
                $la->setData($data ?: []);
            }
        } else {
            $la->setStatus('error')->notify($res['error']);
        }

}
