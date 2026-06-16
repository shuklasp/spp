<?php

namespace SPPMod\SPPAuth;

use SPPMod\SPPDB\SPPDB;

class RateLimiter
{
    public static function hit(string $username, string $ip)
    {
        $db = new SPPDB();
        
        // Find existing record
        $sql = "SELECT id, attempts, TIMESTAMPDIFF(MINUTE, last_attempt, NOW()) as mins_passed 
                FROM " . SPPDB::sppTable('login_attempts') . " 
                WHERE ip_address = ? AND username = ?";
        $res = $db->execute_query($sql, [$ip, $username]);

        if (empty($res)) {
            $db->insertValues(SPPDB::sppTable('login_attempts'), [
                'ip_address' => $ip,
                'username' => $username,
                'attempts' => 1
            ]);
        } else {
            $record = $res[0];
            // If the last attempt was over 15 minutes ago, reset the counter
            if ($record['mins_passed'] > 15) {
                $attempts = 1;
            } else {
                $attempts = $record['attempts'] + 1;
            }

            $sql = "UPDATE " . SPPDB::sppTable('login_attempts') . " SET attempts = ?, last_attempt = NOW() WHERE id = ?";
            $db->execute_query($sql, [$attempts, $record['id']]);
        }
    }

    public static function tooManyAttempts(string $username, string $ip, int $maxAttempts = 5): bool
    {
        $db = new SPPDB();
        $sql = "SELECT attempts, TIMESTAMPDIFF(MINUTE, last_attempt, NOW()) as mins_passed 
                FROM " . SPPDB::sppTable('login_attempts') . " 
                WHERE ip_address = ? AND username = ?";
        $res = $db->execute_query($sql, [$ip, $username]);

        if (!empty($res)) {
            $record = $res[0];
            // Lock out for 15 minutes
            if ($record['attempts'] >= $maxAttempts && $record['mins_passed'] <= 15) {
                return true;
            }
        }
        return false;
    }

    public static function clear(string $username, string $ip)
    {
        $db = new SPPDB();
        $sql = "DELETE FROM " . SPPDB::sppTable('login_attempts') . " WHERE ip_address = ? AND username = ?";
        $db->execute_query($sql, [$ip, $username]);
    }
}
