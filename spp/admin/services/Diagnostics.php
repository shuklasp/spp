<?php

/**
 * Diagnostics API Controller for SPPAdmin
 */

if (!function_exists('live_diagnostics_health')) {
    function live_diagnostics_health($la, $params)
    {
        $res = \SPP\CLI\CommandManager::execute('admin:diagnostics', ['health', '--payload' => json_encode($params), '--json' => '1']);
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
}

if (!function_exists('live_list_queue')) {
    function live_list_queue($la, $params)
    {
        $res = \SPP\CLI\CommandManager::execute('admin:diagnostics', ['list_queue', '--payload' => json_encode($params), '--json' => '1']);
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
}

if (!function_exists('live_get_event_trace')) {
    function live_get_event_trace($la, $params)
    {
        $res = \SPP\CLI\CommandManager::execute('admin:diagnostics', ['get_event_trace', '--payload' => json_encode($params), '--json' => '1']);
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
}

if (!function_exists('live_get_parikshak_trace')) {
    function live_get_parikshak_trace($la, $params)
    {
        $res = \SPP\CLI\CommandManager::execute('admin:diagnostics', ['get_parikshak_trace', '--payload' => json_encode($params), '--json' => '1']);
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
}
