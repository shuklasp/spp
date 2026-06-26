<?php
namespace Lekhak\Modules\LekhakDrupalApi\Controller;

use SPPMod\SPPDB\SPPDB;
use Lekhak\Modules\LekhakDrupalApi\Serializer\JsonApiSerializer;

class CommentController
{

    public function getComments($type)
    {
        $db = new SPPDB();

        $sql = "SELECT c.* FROM lek_comments c";
        $comments = $db->execute_query($sql);

        $data = [];
        if (!empty($comments)) {
            foreach ($comments as $comment) {
                $data[] = JsonApiSerializer::serializeComment($comment, $type);
            }
        }

        return json_encode(JsonApiSerializer::wrapDocument($data, true), JSON_UNESCAPED_SLASHES);
    }
}
