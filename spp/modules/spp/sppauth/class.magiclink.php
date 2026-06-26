<?php

namespace SPPMod\SPPAuth;

use SPPMod\SPPDB\SPPDB;

class MagicLink
{
    /**
     * Generate a new Magic Link token for the given user ID.
     */
    public static function createToken(string $userId, int $expiresInMinutes = 15): string
    {
        $plainToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $plainToken);

        $db = new SPPDB();
        $db->insertValues(SPPDB::sppTable('magic_links'), [
            'user_id' => $userId,
            'token' => $hashedToken,
            'expires_at' => date('Y-m-d H:i:s', strtotime("+$expiresInMinutes minutes"))
        ]);

        return $plainToken;
    }

    /**
     * Consume a Magic Link token to authenticate.
     */
    public static function consumeToken(string $plainToken): ?SPPUser
    {
        $hashedToken = hash('sha256', $plainToken);
        $db = new SPPDB();

        $sql = "SELECT id, user_id FROM " . SPPDB::sppTable('magic_links') . " 
                WHERE token = ? AND (expires_at IS NULL OR expires_at > NOW())";
        $res = $db->execute_query($sql, [$hashedToken]);

        if (!empty($res)) {
            $record = $res[0];

            // Delete token so it can't be reused
            $delSql = "DELETE FROM " . SPPDB::sppTable('magic_links') . " WHERE id = ?";
            $db->execute_query($delSql, [$record['id']]);

            try {
                $user = new SPPUser($record['user_id']);

                // Audit log
                AuditLogger::log('magic_link_login', $user->id, null, "IP: " . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));

                return $user;
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }
}
