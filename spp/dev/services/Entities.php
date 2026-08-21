<?php
/**
 * Entity Management Service Group for SPP Admin
 */

if (!function_exists('live_Entities_List')) {
    function live_Entities_List($la, $params)
    {
    $res = \SPP\CLI\CommandManager::execute('dev:entities', ['list', '--payload' => json_encode($params), '--json' => '1']);
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

if (!function_exists('live_Entities_Save')) {
    function live_Entities_Save($la, $params)
    {
    $res = \SPP\CLI\CommandManager::execute('dev:entities', ['save', '--payload' => json_encode($params), '--json' => '1']);
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

if (!function_exists('live_Entities_Delete')) {
    function live_Entities_Delete($la, $params)
    {
    $res = \SPP\CLI\CommandManager::execute('dev:entities', ['delete', '--payload' => json_encode($params), '--json' => '1']);
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

if (!function_exists('live_Entities_ParseYAML')) {
    function live_Entities_ParseYAML($la, $params)
    {
    $res = \SPP\CLI\CommandManager::execute('dev:entities', ['parseyaml', '--payload' => json_encode($params), '--json' => '1']);
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

if (!function_exists('live_Entities_DumpYAML')) {
    function live_Entities_DumpYAML($la, $params)
    {
    $res = \SPP\CLI\CommandManager::execute('dev:entities', ['dumpyaml', '--payload' => json_encode($params), '--json' => '1']);
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
