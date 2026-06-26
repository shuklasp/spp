<?php

namespace SPPMod\SPPAuth;

use SPPMod\SPPDB\SPPDB;

class RateLimiter
{
    public static function hit(string $username, string $ip)
    {
        $db = new SPPDB();

        // Find existing record
        $sql = "SELECT id, attempts, last_attempt FROM " . SPPDB::sppTable('login_attempts') . " 
                WHERE ip_address = ? AND username = ?";
        $res = $db->execute_query($sql, [$ip, $username]);

        if (empty($res)) {
            $db->insertValues(SPPDB::sppTable('login_attempts'), [
                'ip_address' => $ip,
                'username' => $username,
                'attempts' => 1,
                'last_attempt' => date('Y-m-d H:i:s')
            ]);
        } else {
            $record = $res[0];
            $mins_passed = (time() - strtotime($record['last_attempt'])) / 60;
            // If the last attempt was over 15 minutes ago, reset the counter
            if ($mins_passed > 15) {
                $attempts = 1;
            } else {
                $attempts = $record['attempts'] + 1;
            }

            $sql = "UPDATE " . SPPDB::sppTable('login_attempts') . " SET attempts = ?, last_attempt = ? WHERE id = ?";
            $db->execute_query($sql, [$attempts, date('Y-m-d H:i:s'), $record['id']]);
        }
    }

    public static function tooManyAttempts(string $username, string $ip, int $maxAttempts = 5): bool
    {
        $db = new SPPDB();
        $sql = "SELECT attempts, last_attempt FROM " . SPPDB::sppTable('login_attempts') . " 
                WHERE ip_address = ? AND username = ?";
        $res = $db->execute_query($sql, [$ip, $username]);

        if (!empty($res)) {
            $record = $res[0];
            $mins_passed = (time() - strtotime($record['last_attempt'])) / 60;
            // Lock out for 15 minutes
            if ($record['attempts'] >= $maxAttempts && $mins_passed <= 15) {
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
