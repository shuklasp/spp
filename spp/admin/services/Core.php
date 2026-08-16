<?php
/**
 * Core Management Service Group for SPP Admin
 */

if (!function_exists('live_Core_ListApps')) {
    function live_Core_ListApps($la, $params)
    {
        $res = \SPP\CLI\CommandManager::execute('admin:core', ['listapps', '--payload' => json_encode($params), '--json' => '1']);
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

if (!function_exists('live_Core_RunCommand')) {
    function live_Core_RunCommand($la, $params)
    {
        $res = \SPP\CLI\CommandManager::execute('admin:core', ['runcommand', '--payload' => json_encode($params), '--json' => '1']);
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

if (!function_exists('live_Core_GetSystemInfo')) {
    function live_Core_GetSystemInfo($la, $params)
    {
        $res = \SPP\CLI\CommandManager::execute('admin:core', ['getsysteminfo', '--payload' => json_encode($params), '--json' => '1']);
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

if (!function_exists('live_Core_GetBridgeInfo')) {
    function live_Core_GetBridgeInfo($la, $params)
    {
        $res = \SPP\CLI\CommandManager::execute('admin:core', ['getbridgeinfo', '--payload' => json_encode($params), '--json' => '1']);
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

if (!function_exists('live_Core_SetupBridge')) {
    function live_Core_SetupBridge($la, $params)
    {
        $res = \SPP\CLI\CommandManager::execute('admin:core', ['setupbridge', '--payload' => json_encode($params), '--json' => '1']);
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

if (!function_exists('live_Core_TestBridge')) {
    function live_Core_TestBridge($la, $params)
    {
        $res = \SPP\CLI\CommandManager::execute('admin:core', ['testbridge', '--payload' => json_encode($params), '--json' => '1']);
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

if (!function_exists('live_Core_CompileRegistry')) {
    function live_Core_CompileRegistry($la, $params)
    {
        $res = \SPP\CLI\CommandManager::execute('admin:core', ['compileregistry', '--payload' => json_encode($params), '--json' => '1']);
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
