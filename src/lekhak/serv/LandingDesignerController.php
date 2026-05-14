<?php
namespace App\Lekhak\Serv;

use SPPMod\Lekhak\Core\LandingPage;
use SPPMod\Lekhak\Core\LandingBlock;
use SPPMod\SPPView\ViewPage;
use SPPMod\SPPView\ViewFormBuilder;

/**
 * Class LandingDesignerController
 * Handles the design and management of landing pages.
 */
class LandingDesignerController extends AdminController
{
    public function list()
    {
        $this->ensureSchema();
        $pages = LandingPage::find_all();
        return $this->render("landing/list", [
            'title' => 'Landing Pages',
            'subtitle' => 'Manage your entry points and marketing views.',
            'pages' => $pages,
            'view_name' => 'landing/list'
        ]);
    }

    protected function ensureSchema()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        
        // Landing Pages Table
        $db->execute_query('CREATE TABLE IF NOT EXISTS ' . \SPPMod\SPPDB\SPPDB::sppTable('landing_pages') . ' (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            alias VARCHAR(255) NOT NULL UNIQUE,
            bundle VARCHAR(50) NOT NULL,
            body LONGTEXT,
            author_id BIGINT,
            status VARCHAR(20),
            langcode VARCHAR(10),
            translation_id BIGINT,
            created DATETIME,
            changed DATETIME,
            is_default TINYINT(1) DEFAULT 0,
            layout_id VARCHAR(50)
        )');

        // Landing Blocks Table
        $db->execute_query('CREATE TABLE IF NOT EXISTS ' . \SPPMod\SPPDB\SPPDB::sppTable('landing_blocks') . ' (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            page_id BIGINT NOT NULL,
            block_type VARCHAR(50) NOT NULL,
            data LONGTEXT,
            weight INT DEFAULT 0,
            region VARCHAR(50),
            created DATETIME
        )');

        // Audit Logs Table (to prevent warnings)
        $db->execute_query('CREATE TABLE IF NOT EXISTS ' . \SPPMod\SPPDB\SPPDB::sppTable('audit_logs') . ' (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(100),
            entity_id VARCHAR(50),
            action VARCHAR(50),
            old_data LONGTEXT,
            new_data LONGTEXT,
            user_id VARCHAR(50),
            timestamp DATETIME
        )');
    }

    public function create()
    {
        $this->ensureSchema();
        $page = new LandingPage();
        $builder = new ViewFormBuilder($page);
        
        // Use the premium Glass theme for better clarity and aesthetics
        \SPPMod\SPPView\SPPViewForm_Element::setTheme('glass_admin');
        
        $form = $builder->build();
        
        // Ensure the form posts back to this controller, not the root index.php
        $form->setAttribute('action', $this->getAppRoot() . "/admin/landing/create");

        $sub = $form->isSubmitted() ? 'YES' : 'NO';
        @file_put_contents(SPP_LOG_DIR . '/query_log.txt', date('[Y-m-d H:i:s] ') . "LandingDesignerController::create - isSubmitted: $sub\n", FILE_APPEND);
        if ($form->isSubmitted()) {
            try {
                if ($form->save()) {
                    if (session_status() === PHP_SESSION_NONE) session_start();
                    $_SESSION['flash_success'] = "Landing page '{$page->title}' has been created successfully.";
                    @file_put_contents(SPP_LOG_DIR . '/query_log.txt', date('[Y-m-d H:i:s] ') . "LandingDesignerController::create - Save SUCCESS. Redirecting to " . $this->getAppRoot() . "/admin/landing\n", FILE_APPEND);
                    header("Location: " . $this->getAppRoot() . "/admin/landing");
                    exit;
                } else {
                    // Form validation failed or save failed
                    $errors = $form->getErrors();
                    error_log("LandingDesignerController::create - Save failed. Errors: " . json_encode($errors));
                }
            } catch (\Exception $e) {
                error_log("LandingPage creation exception: " . $e->getMessage());
            }
        }

        return $this->render("landing/form", [
            'title' => 'Create Landing Page',
            'subtitle' => 'Define the basic settings for your new entry point.',
            'form' => $form,
            'view_name' => 'landing/create'
        ]);
    }

    public function design($id)
    {
        $this->ensureSchema();
        try {
            $page = new LandingPage($id);
        } catch (\Exception $e) {
            header("Location: " . $this->getAppRoot() . "/admin/landing");
            exit;
        }
        if (!$page->id) {
            header("Location: " . $this->getAppRoot() . "/admin/landing");
            exit;
        }

        $blocks = $page->getBlocks();

        return $this->render("landing/designer", [
            'title' => 'Landing Designer',
            'subtitle' => "Designing layout for: {$page->title}",
            'page' => $page,
            'blocks' => $blocks
        ]);
    }

    public function addBlock($page_id)
    {
        $type = $_GET['type'] ?? 'text';
        $block = new LandingBlock();
        $block->page_id = $page_id;
        $block->block_type = $type;
        $block->weight = (int)($_GET['weight'] ?? (LandingBlock::count(['page_id' => $page_id]) + 1));
        $block->region = $_GET['region'] ?? 'main';
        
        // Default data based on type
        $defaultData = match($type) {
            'hero' => ['title' => 'Welcome', 'subtitle' => 'This is a hero section', 'button_text' => 'Get Started'],
            'features' => ['title' => 'Our Features', 'items' => [['icon' => '🚀', 'text' => 'Fast'], ['icon' => '🛡️', 'text' => 'Secure']]],
            'cta' => ['text' => 'Ready to start?', 'button_text' => 'Join Now'],
            'dynamic_list' => ['title' => 'Recent Articles', 'entity_type' => 'node', 'conditions' => ['bundle' => 'article'], 'limit' => 5],
            default => ['content' => 'Enter your text here...']
        };

        // Add standard theme config
        $defaultData['_style'] = [
            'bg_type' => 'default',
            'padding' => 'medium',
            'text_align' => 'left',
            'animation' => 'none'
        ];

        $block->setContent($defaultData);
        $block->save();

        if (!empty($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
            \SPP\Response::json([
                'success' => true,
                'block' => [
                    'id' => $block->id,
                    'block_type' => strtoupper($block->block_type),
                    'title' => $defaultData['title'] ?? '',
                    'preview' => substr(strip_tags($defaultData['content'] ?? $defaultData['text'] ?? ''), 0, 100)
                ]
            ]);
        }

        \SPP\Response::redirect($this->getAppRoot() . "/admin/landing/design/" . $page_id);
    }

    public function editBlock($id)
    {
        try {
            $block = new LandingBlock($id);
        } catch (\Exception $e) {
            if (!empty($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
                \SPP\Response::json(['success' => false, 'error' => 'Block not found']);
            }
            exit;
        }
        if (!$block->id) exit;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST['config'] ?? [];
            // Basic condition parsing for dynamic list
            if ($block->block_type === 'dynamic_list' && isset($data['conditions_raw'])) {
                $lines = explode("\n", $data['conditions_raw']);
                $conditions = [];
                foreach ($lines as $line) {
                    $parts = explode('=', $line);
                    if (count($parts) === 2) {
                        $conditions[trim($parts[0])] = trim($parts[1]);
                    }
                }
                $data['conditions'] = $conditions;
            }
            
            $block->setContent($data);
            $block->save();

            if (!empty($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
                \SPP\Response::json([
                    'success' => true,
                    'title' => $data['title'] ?? '',
                    'preview' => substr(strip_tags($data['content'] ?? $data['text'] ?? ''), 0, 100)
                ]);
            }

            \SPP\Response::redirect($this->getAppRoot() . "/admin/landing/design/" . $block->page_id);
        }

        $viewName = !empty($_GET['ajax']) ? "admin/landing/block-edit-modal" : "admin/landing/block-edit";
        return $this->render($viewName, [
            'block' => $block,
            'content' => $block->getContent()
        ]);
    }

    public function deleteBlock($id)
    {
        try {
            $block = new LandingBlock($id);
            $page_id = $block->page_id;
            $block->delete();
        } catch (\Exception $e) {
            if (!empty($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
                \SPP\Response::json(['success' => false, 'error' => 'Block not found']);
            }
            $page_id = $_GET['page_id'] ?? null;
            if ($page_id) {
                \SPP\Response::redirect($this->getAppRoot() . "/admin/landing/design/" . $page_id);
            } else {
                \SPP\Response::redirect($this->getAppRoot() . "/admin/landing");
            }
            exit;
        }

        if (!empty($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
            \SPP\Response::json(['success' => true]);
        }

        \SPP\Response::redirect($this->getAppRoot() . "/admin/landing/design/" . $page_id);
    }

    public function moveBlock($id)
    {
        try {
            $block = new LandingBlock($id);
        } catch (\Exception $e) {
            exit;
        }
        if (!$block->id) exit;
        
        $region = $_GET['region'] ?? 'main';
        $weight = $_GET['weight'] ?? $block->weight;
        
        $block->region = $region;
        $block->weight = (int)$weight;
        $block->save();
        
        header("Location: " . $this->getAppRoot() . "/admin/landing/design/" . $block->page_id);
        exit;
    }

    public function setAsDefault($id)
    {
        try {
            $page = new LandingPage($id);
            $page->setAsDefault();
        } catch (\Exception $e) {}

        header("Location: " . $this->getAppRoot() . "/admin/landing");
        exit;
    }

    public function updateLayout($page_id)
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data || !isset($data['blocks'])) {
            \SPP\Response::json(['status' => 'error', 'message' => 'Invalid data'], 400);
        }

        foreach ($data['blocks'] as $b) {
            try {
                $block = new LandingBlock($b['id']);
                if ($block->id) {
                    $block->region = $b['region'];
                    $block->weight = (int)$b['weight'];
                    $block->save();
                }
            } catch (\Exception $e) {}
        }

        \SPP\Response::json(['status' => 'success']);
    }
}
