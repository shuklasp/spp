<?php

namespace SPPMod\SPPAuth;

/**
 * class SCIMHandler
 *
 * Skeleton implementation of the SCIM 2.0 protocol for automated identity provisioning.
 * Used by external IdPs (Azure AD, Okta) to create, update, and disable users/groups in SPP.
 */
class SCIMHandler
{
    /**
     * Process incoming SCIM requests.
     */
    public function handleRequest(string $method, string $endpoint, array $payload)
    {
        // Require Bearer Token validation specifically scoped for SCIM
        $guard = new TokenGuard();
        if (!$guard->check() || !$guard->can('scim_provisioning')) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        switch ($endpoint) {
            case 'Users':
                if ($method === 'POST') {
                    $this->createUser($payload);
                } elseif ($method === 'PUT' || $method === 'PATCH') {
                    $this->updateUser($payload['id'] ?? null, $payload);
                }
                break;
            case 'Groups':
                // Implement group syncing logic here
                break;
            default:
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
        }
    }

    private function createUser(array $payload)
    {
        // Parse SCIM payload mapping to SPPUser
        $userName = $payload['userName'] ?? '';
        $email = $payload['emails'][0]['value'] ?? $userName;
        $firstName = $payload['name']['givenName'] ?? '';
        $lastName = $payload['name']['familyName'] ?? '';
        $active = $payload['active'] ?? true;

        if (empty($userName)) {
            http_response_code(400);
            echo json_encode(['error' => 'userName is required']);
            return;
        }

        $user = new SPPUser();
        $user->set('username', $userName);
        $user->set('email', $email);
        $user->set('first_name', $firstName);
        $user->set('last_name', $lastName);
        $user->set('status', $active ? 'active' : 'inactive');
        
        // Random secure password for SCIM provisioned users
        $user->set('password', password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT));

        try {
            $user->save();
        } catch (\Exception $e) {
            http_response_code(409);
            echo json_encode(['error' => 'User already exists or save failed']);
            return;
        }

        http_response_code(201);
        header('Content-Type: application/scim+json');
        echo json_encode([
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
            'id' => (string)$user->id,
            'userName' => $userName,
            'active' => $active
        ]);
    }

    private function updateUser(?string $id, array $payload)
    {
        if (!$id) {
            http_response_code(400);
            return;
        }

        try {
            $user = new SPPUser($id);
            if (!$user->id) {
                http_response_code(404);
                return;
            }

            if (isset($payload['userName'])) $user->set('username', $payload['userName']);
            if (isset($payload['emails'][0]['value'])) $user->set('email', $payload['emails'][0]['value']);
            if (isset($payload['name']['givenName'])) $user->set('first_name', $payload['name']['givenName']);
            if (isset($payload['name']['familyName'])) $user->set('last_name', $payload['name']['familyName']);
            if (isset($payload['active'])) $user->set('status', $payload['active'] ? 'active' : 'inactive');

            $user->save();

            http_response_code(200);
            header('Content-Type: application/scim+json');
            echo json_encode([
                'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
                'id' => (string)$user->id,
                'userName' => $user->username,
                'active' => $user->get('status') === 'active'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
