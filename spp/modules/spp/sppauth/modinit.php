<?php
declare(strict_types=1);

namespace SPPMod\SPPAuth;

// Register SPPAuth as the authentication provider for SPPAPI if it exists.
if (class_exists('\\SPPMod\\SPPAPI\\SPPAPI')) {
    \SPPMod\SPPAPI\SPPAPI::setAuthValidator(['\\SPPMod\\SPPAuth\\SPPAuth', 'authSessionExists']);
}

if (class_exists('\\SPP\\SPPEvent')) {
    \SPP\SPPEvent::listen('auth.verify_credentials', function(\SPP\EventParams $params) {
        $username = $params->get('username');
        $password = $params->get('password');
        
        if ($username && $password) {
            $user = \SPPMod\SPPAuth\SPPUser::find_one(['username' => $username]);
            if ($user && password_verify($password, $user->password)) {
                $params->set('authenticated', true);
                $params->set('user_id', $user->id);
            }
        }
    });
}
