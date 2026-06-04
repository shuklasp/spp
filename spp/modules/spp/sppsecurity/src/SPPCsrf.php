<?php
namespace SPPMod\Sppsecurity;

class SPPCsrf {
    public function generate(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['spp_csrf_token'])) {
            $_SESSION['spp_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['spp_csrf_token'];
    }

    public function validate(string $token): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['spp_csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['spp_csrf_token'], $token);
    }
}
