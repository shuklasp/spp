<?php
/**
 * Translation Admin Services
 * Integrates with SPPLang to support scanning, listing and saving translated items.
 */

if (!function_exists('live_spplang_get')) {
    function live_spplang_get($la, $p) {
    $res = \SPP\CLI\CommandManager::execute('dev:spplang', ['get', '--payload' => json_encode($params), '--json' => '1']);
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
}

if (!function_exists('live_spplang_save')) {
    function live_spplang_save($la, $p) {
    $res = \SPP\CLI\CommandManager::execute('dev:spplang', ['save', '--payload' => json_encode($params), '--json' => '1']);
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
}

if (!function_exists('live_spplang_scan')) {
    function live_spplang_scan($la, $p) {
    $res = \SPP\CLI\CommandManager::execute('dev:spplang', ['scan', '--payload' => json_encode($params), '--json' => '1']);
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
}
