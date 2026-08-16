<?php

namespace SPPMod\SPPAuth\Commands;

use SPP\CLI\Command;
use SPP\CLI\Console;
use SPPMod\SPPAuth\MagicLink;
use SPPMod\SPPAuth\SPPUser;

class AuthMagicLinkCommand extends Command
{
    protected string $signature = 'auth:magiclink {email}';
    protected string $description = 'Generate a one-time passwordless Magic Link for a user';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $email = $args['email'] ?? null;
        if (!$email) {
            Console::error("Error: email argument is required.");
            Console::line("Usage: php spp.php auth:magiclink <email>");
            return;
        }

        try {
            // Find user by email
            $db = new \SPPMod\SPPDB\SPPDB();
            $sql = "SELECT id FROM " . \SPPMod\SPPDB\SPPDB::sppTable('users') . " WHERE email = ? LIMIT 1";
            $res = $db->execute_query($sql, [$email]);

            if (empty($res)) {
                Console::error("User not found with email: $email");
                return;
            }

            $userId = $res[0]['id'];
            $user = new SPPUser($userId);

            if (!$user->getId()) {
                Console::error("Failed to load SPPUser instance.");
                return;
            }

            Console::info("Generating Magic Link for {$user->get('username')} ($email)...");

            $token = MagicLink::createToken($userId, 15);

            // In a real system, we'd email this. For the CLI, we output it.
            $loginUrl = \SPP\Config::get('app.url') . "/spp/admin/?magic_token=" . urlencode($token);

            Console::success("Magic Link generated successfully! (Valid for 15 minutes)");
            Console::line("");
            Console::line("Link: " . $loginUrl);
            Console::line("Token: " . $token);
            Console::line("");
            Console::warning("Do not share this link with anyone else!");

            return;
        } catch (\Exception $e) {
            Console::error("Command failed: " . $e->getMessage());
            return;
        }
    }
}
