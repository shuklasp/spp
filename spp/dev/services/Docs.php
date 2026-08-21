<?php

function live_get_codebase_structure($la, $p)
{
    $res = \SPP\CLI\CommandManager::execute('dev:docs', ['get_codebase_structure', '--payload' => json_encode($params), '--json' => '1']);
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

function live_get_file_content($la, $p)
{
    $res = \SPP\CLI\CommandManager::execute('dev:docs', ['get_file_content', '--payload' => json_encode($params), '--json' => '1']);
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
