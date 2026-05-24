<?php
namespace App\Lekhak\Serv;

use SPPMod\SPPAuth\SPPAuth;
use SPPMod\Lekhak\Core\LekhakNode;
use SPPMod\SPPDB\SPPDB;

class TranslationController extends AdminController
{
    public function index($id)
    {
        $node = LekhakNode::find_one(['id' => $id]);
        if (!$node) {
            echo "Node not found.";
            return;
        }

        $languages = [
            'es' => 'Spanish',
            'fr' => 'French',
            'hi' => 'Hindi (Regional)'
        ];

        $targetLang = $_GET['lang'] ?? 'es';
        if (!isset($languages[$targetLang])) {
            $targetLang = 'es';
        }

        // Fetch existing translations for this language
        $db = new SPPDB();
        $table = SPPDB::sppTable('entity_translations');
        $translations = [];
        if ($db->tableExists($table)) {
            $res = $db->exec_squery(
                "SELECT translated_data FROM %tab% WHERE entity_class = ? AND entity_id = ? AND language_code = ?",
                $table,
                [LekhakNode::class, $id, $targetLang]
            );
            if (!empty($res) && !empty($res[0]['translated_data'])) {
                $translations = json_decode($res[0]['translated_data'], true) ?: [];
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            if ($action === 'save_translation') {
                $field = $_POST['field'] ?? '';
                $value = $_POST['value'] ?? '';
                if ($field) {
                    $node->setLanguage($targetLang);
                    $node->{$field} = $value;
                    $node->save();
                }
            }
            header("Location: " . \SPP\App::getBaseUrl('lekhak') . "/admin/content/{$id}/translate?lang={$targetLang}");
            exit;
        }

        return $this->render('translate', [
            'node' => $node,
            'languages' => $languages,
            'targetLang' => $targetLang,
            'translations' => $translations,
            'title' => 'Translate: ' . $node->title,
            'subtitle' => 'Manage translations for this document stream.'
        ]);
    }
}
