<?php
namespace Lekhak\Modules\LekhakDrupalApi\Controller;

use SPPMod\SPPDB\SPPDB;
use Lekhak\Modules\LekhakDrupalApi\Serializer\JsonApiSerializer;

class TermController {
    
    public function getTerms($vocabulary) {
        $db = new SPPDB();
        
        $sql = "SELECT t.* FROM lek_terms t WHERE t.vid = ?";
        $terms = $db->execute_query($sql, [$vocabulary]);
        
        $data = [];
        if (!empty($terms)) {
            foreach ($terms as $term) {
                $data[] = JsonApiSerializer::serializeTerm($term, $vocabulary);
            }
        }
        
        return json_encode(JsonApiSerializer::wrapDocument($data, true), JSON_UNESCAPED_SLASHES);
    }
}
