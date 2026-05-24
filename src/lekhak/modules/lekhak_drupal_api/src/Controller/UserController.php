<?php
namespace Lekhak\Modules\LekhakDrupalApi\Controller;

use SPPMod\SPPDB\SPPDB;
use Lekhak\Modules\LekhakDrupalApi\Serializer\JsonApiSerializer;

class UserController {
    
    public function getUsers() {
        $db = new SPPDB();
        
        $sql = "SELECT id, username, email, created_at, updated_at, status FROM users";
        $users = $db->execute_query($sql);
        
        $data = [];
        if (!empty($users)) {
            foreach ($users as $user) {
                $data[] = JsonApiSerializer::serializeUser($user);
            }
        }
        
        return json_encode(JsonApiSerializer::wrapDocument($data, true), JSON_UNESCAPED_SLASHES);
    }
}
