<?php
namespace App\Lekhak\Serv;

use SPPMod\SPPAuth\SPPAuth;
use SPPMod\Lekhak\Core\LekhakNode;
use SPPMod\SPPDB\SPPDB;

class RevisionsController extends AdminController
{
    public function index($id)
    {

        $node = LekhakNode::find_one(['id' => $id]);
        if (!$node) {
            echo "Node not found.";
            return;
        }

        $db = new SPPDB();
        $table = SPPDB::sppTable('entity_revisions');

        $revisions = [];
        if ($db->tableExists($table)) {
            $revisions = $db->exec_squery(
                "SELECT * FROM %tab% WHERE entity_type = ? AND entity_id = ? ORDER BY revision_timestamp DESC",
                $table,
                [LekhakNode::class, $id]
            );
        }

        return $this->render('revisions_list', [
            'node' => $node,
            'revisions' => $revisions,
            'title' => 'Revisions for: ' . $node->title,
            'subtitle' => 'View history and revert to previous states.'
        ]);
    }

    public function revert($id, $revId)
    {

        $node = LekhakNode::find_one(['id' => $id]);
        if (!$node) {
            echo "Node not found.";
            return;
        }

        $db = new SPPDB();
        $table = SPPDB::sppTable('entity_revisions');

        if ($db->tableExists($table)) {
            $res = $db->exec_squery("SELECT * FROM %tab% WHERE id = ?", $table, [$revId]);
            if (!empty($res)) {
                $rev = $res[0];
                $delta = json_decode($rev['state_delta'], true);

                if (is_array($delta)) {
                    // Revert properties
                    foreach ($delta as $attr => $changes) {
                        if (isset($changes['old'])) {
                            $node->{$attr} = $changes['old'];
                        }
                    }
                    $node->save();
                    // Saving will trigger a new revision automatically!
                }
            }
        }

        header("Location: /school1/lekhak/admin/content/{$id}/revisions");
        exit;
    }
}
