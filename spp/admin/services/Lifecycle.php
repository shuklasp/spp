<?php
/**
 * Lifecycle & Sync Service Group for SPP Admin
 */

function live_lifecycle_receive($la, $params) {
        $res = \SPP\CLI\CommandManager::execute('admin:lifecycle', ['receive', '--payload' => json_encode($params), '--json' => '1']);
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

function isSafeLifecyclePath(string $path): bool {
    $normalized = str_replace('\\', '/', $path);
    if ($normalized === '' || preg_match('/^([a-zA-Z]:|\/)/', $normalized)) {
        return false;
    }
    if (str_contains($normalized, '../') || str_contains($normalized, '/..') || $normalized === '..') {
        return false;
    }
    return true;
}

function live_lifecycle_backup($la, $params) {
        $res = \SPP\CLI\CommandManager::execute('admin:lifecycle', ['backup', '--payload' => json_encode($params), '--json' => '1']);
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

function live_Lifecycle_GetEnvs($la, $params) {
        $res = \SPP\CLI\CommandManager::execute('admin:lifecycle', ['getenvs', '--payload' => json_encode($params), '--json' => '1']);
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

function live_Lifecycle_GetSecurity($la, $params) {
        $res = \SPP\CLI\CommandManager::execute('admin:lifecycle', ['getsecurity', '--payload' => json_encode($params), '--json' => '1']);
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

function live_lifecycle_rotatetoken($la, $params) {
        $res = \SPP\CLI\CommandManager::execute('admin:lifecycle', ['rotatetoken', '--payload' => json_encode($params), '--json' => '1']);
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

function live_lifecycle_updatelist($la, $params) {
        $res = \SPP\CLI\CommandManager::execute('admin:lifecycle', ['updatelist', '--payload' => json_encode($params), '--json' => '1']);
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

function live_lifecycle_updaterun($la, $params) {
        $res = \SPP\CLI\CommandManager::execute('admin:lifecycle', ['updaterun', '--payload' => json_encode($params), '--json' => '1']);
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
function live_lifecycle_config_target($la, $params) {
        $res = \SPP\CLI\CommandManager::execute('admin:lifecycle', ['config_target', '--payload' => json_encode($params), '--json' => '1']);
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

function live_lifecycle_save_target($la, $params) {
        $res = \SPP\CLI\CommandManager::execute('admin:lifecycle', ['save_target', '--payload' => json_encode($params), '--json' => '1']);
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

function live_sys_upgrade($la, $params) {
        $res = \SPP\CLI\CommandManager::execute('admin:lifecycle', ['sys_upgrade', '--payload' => json_encode($params), '--json' => '1']);
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

function live_lifecycle_deploy_history($la, $params) {
        $res = \SPP\CLI\CommandManager::execute('admin:lifecycle', ['deploy_history', '--payload' => json_encode($params), '--json' => '1']);
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

function live_lifecycle_list_backups($la, $params) {
        $res = \SPP\CLI\CommandManager::execute('admin:lifecycle', ['list_backups', '--payload' => json_encode($params), '--json' => '1']);
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

function live_lifecycle_remote_logs($la, $params) {
        $res = \SPP\CLI\CommandManager::execute('admin:lifecycle', ['remote_logs', '--payload' => json_encode($params), '--json' => '1']);
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

function live_lifecycle_remote_run($la, $params) {
        $res = \SPP\CLI\CommandManager::execute('admin:lifecycle', ['remote_run', '--payload' => json_encode($params), '--json' => '1']);
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

function live_lifecycle_health_check($la, $params) {
        $res = \SPP\CLI\CommandManager::execute('admin:lifecycle', ['health_check', '--payload' => json_encode($params), '--json' => '1']);
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

function live_lifecycle_get_webhooks($la, $params) {
        $res = \SPP\CLI\CommandManager::execute('admin:lifecycle', ['get_webhooks', '--payload' => json_encode($params), '--json' => '1']);
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

function live_lifecycle_save_webhooks($la, $params) {
        $res = \SPP\CLI\CommandManager::execute('admin:lifecycle', ['save_webhooks', '--payload' => json_encode($params), '--json' => '1']);
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

function live_lifecycle_test_webhook($la, $params) {
        $res = \SPP\CLI\CommandManager::execute('admin:lifecycle', ['test_webhook', '--payload' => json_encode($params), '--json' => '1']);
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

function live_lifecycle_cluster_status($la, $params) {
        $res = \SPP\CLI\CommandManager::execute('admin:lifecycle', ['cluster_status', '--payload' => json_encode($params), '--json' => '1']);
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

function live_lifecycle_maintenance_toggle($la, $params) {
        $res = \SPP\CLI\CommandManager::execute('admin:lifecycle', ['maintenance_toggle', '--payload' => json_encode($params), '--json' => '1']);
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
