<?php
namespace SPPMod\SPPAuth\Commands;

use SPP\CLI\Command;
use SPPMod\SPPAuth\TokenGuard;
use SPPMod\SPPAuth\SPPUser;
use SPPMod\SPPDB\SPPDB;

class AuthTokensCommand extends Command {
    protected string $name = 'auth:tokens';
    protected string $description = 'Manage Personal Access Tokens for API Authentication';

    public function execute(array $args): void {
        $action = $args[2] ?? 'list';

        switch ($action) {
            case 'generate':
                $this->generateToken($args);
                break;
            case 'revoke':
                $this->revokeToken($args);
                break;
            case 'list':
            default:
                $this->listTokens($args);
                break;
        }
    }

    private function generateToken(array $args): void {
        $userId = $args[3] ?? null;
        if (!$userId) {
            echo "[ERROR] User ID is required.\n";
            echo "Usage: php spp.php auth:tokens generate <userid> [\"Token Name\"]\n";
            return;
        }

        $name = $args[4] ?? 'CLI Generated Key';

        try {
            $user = new SPPUser($userId);
            if (!$user->id) {
                echo "[ERROR] User '$userId' not found.\n";
                return;
            }

            $token = TokenGuard::createToken($user, $name);
            echo "\n\033[32m[SUCCESS]\033[0m API Key Generated Successfully!\n";
            echo "--------------------------------------------------------\n";
            echo "Token Name: $name\n";
            echo "User ID:    $userId\n";
            echo "Key:        \033[1m$token\033[0m\n";
            echo "--------------------------------------------------------\n";
            echo "\033[33mPlease copy this key now. It will not be shown again.\033[0m\n\n";
        } catch (\Exception $e) {
            echo "[ERROR] Failed to generate token: " . $e->getMessage() . "\n";
        }
    }

    private function revokeToken(array $args): void {
        $tokenId = $args[3] ?? null;
        if (!$tokenId) {
            echo "[ERROR] Token ID is required.\n";
            echo "Usage: php spp.php auth:tokens revoke <token_id>\n";
            return;
        }

        try {
            $db = new SPPDB();
            $db->execute_query('DELETE FROM ' . SPPDB::sppTable('personal_access_tokens') . ' WHERE id = ?', [$tokenId]);
            echo "\033[32m[SUCCESS]\033[0m Token #$tokenId has been revoked.\n";
        } catch (\Exception $e) {
            echo "[ERROR] Failed to revoke token: " . $e->getMessage() . "\n";
        }
    }

    private function listTokens(array $args): void {
        $userIdInput = $args[3] ?? null;
        $userId = null;

        if ($userIdInput) {
            $user = new SPPUser($userIdInput);
            if (!$user->id) {
                echo "[ERROR] User '$userIdInput' not found.\n";
                return;
            }
            $userId = $user->id;
        }
        
        try {
            $db = new SPPDB();
            
            $sql = "SELECT id, userid, name, created_at, expires_at 
                    FROM " . SPPDB::sppTable('personal_access_tokens');
            $params = [];
            
            if ($userId) {
                $sql .= " WHERE userid = ?";
                $params[] = $userId;
                echo "Listing API Keys for User: $userId\n\n";
            } else {
                echo "Listing All API Keys\n";
                echo "Tip: Run `php spp.php auth:tokens list <userid>` to filter by user.\n\n";
            }
            
            $sql .= " ORDER BY created_at DESC";
            $tokens = $db->execute_query($sql, $params);

            if (empty($tokens)) {
                echo "No API keys found.\n";
                return;
            }

            $headers = ['ID', 'User ID', 'Name', 'Created At', 'Status'];
            $rows = [];
            
            foreach ($tokens as $t) {
                $status = (!$t['expires_at'] || strtotime($t['expires_at']) > time()) ? "\033[32mActive\033[0m" : "\033[31mExpired\033[0m";
                $rows[] = [
                    $t['id'],
                    $t['userid'],
                    $t['name'] ?? 'API Key',
                    $t['created_at'],
                    $status
                ];
            }
            
            // Re-using the printTable function defined in spp.php
            if (function_exists('printTable')) {
                printTable($headers, $rows);
            } else {
                // Fallback basic output if printTable is not available
                foreach ($rows as $row) {
                    echo implode(' | ', $row) . "\n";
                }
            }
        } catch (\Exception $e) {
            echo "[ERROR] Failed to list tokens: " . $e->getMessage() . "\n";
        }
    }
}
