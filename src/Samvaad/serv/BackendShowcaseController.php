<?php
namespace App\Samvaad\Serv;

use SPPMod\SPPView\ViewController;
use SPPMod\SPPView\Attributes\Route;
use App\Samvaad\Entities\ShowcaseItem;
use SPPMod\SPPDB\SPPDB;

/**
 * ============================================================================
 * BackendShowcaseController — Non-SPPUX Enterprise Features
 * ============================================================================
 * Demonstrates: Attribute Routing, ORM, CQRS, Workflows, HTMX Content Negotiation
 */
#[Route('/backend-showcase')]
class BackendShowcaseController extends ViewController
{
    #[Route('', method: 'GET', name: 'backend.showcase.index')]
    public function index()
    {
        return $this->renderView('backend_showcase.index');
    }

    #[Route('/partial/intro', method: 'GET')]
    public function partialIntro()
    {
        return $this->renderPartial('backend_showcase.partials.intro');
    }

    /* ------------------------------------------------------------------------
     * ORM & ACTIVE RECORD SHOWCASE
     * ------------------------------------------------------------------------ */
    
    #[Route('/partial/orm', method: 'GET')]
    public function partialOrm()
    {
        \App\Samvaad\Entities\ShowcaseItem::install();
        $items = ShowcaseItem::find_all();
        return $this->renderPartial('backend_showcase.partials.orm', ['items' => $items]);
    }

    #[Route('/orm/create', method: 'POST')]
    public function createItem()
    {
        $title = $_POST['title'] ?? 'New Item';
        $description = $_POST['description'] ?? '';

        $item = new ShowcaseItem();
        $item->title = $title;
        $item->description = $description;
        $item->save();

        // Return updated table via HTMX
        $items = ShowcaseItem::find_all();
        return $this->renderPartial('backend_showcase.partials.orm_table', ['items' => $items]);
    }

    #[Route('/orm/delete/{id}', method: 'DELETE')]
    public function deleteItem(int $id)
    {
        $item = ShowcaseItem::find_one(['id' => $id]);
        if ($item) {
            $item->delete();
        }

        $items = ShowcaseItem::find_all();
        return $this->renderPartial('backend_showcase.partials.orm_table', ['items' => $items]);
    }

    /* ------------------------------------------------------------------------
     * CQRS EVENT STORE SHOWCASE
     * ------------------------------------------------------------------------ */
    
    #[Route('/partial/cqrs', method: 'GET')]
    public function partialCqrs()
    {
        // Mock CQRS Events from session for demonstration
        $events = $_SESSION['cqrs_events'] ?? [];
        return $this->renderPartial('backend_showcase.partials.cqrs', ['events' => $events]);
    }

    #[Route('/cqrs/event', method: 'POST')]
    public function appendEvent()
    {
        $eventName = $_POST['event_name'] ?? 'UnknownEvent';
        $payload = $_POST['payload'] ?? '{}';
        
        $events = $_SESSION['cqrs_events'] ?? [];
        
        // Prepend to show latest first
        array_unshift($events, [
            'name' => $eventName,
            'payload' => $payload,
            'time' => date('Y-m-d H:i:s')
        ]);
        
        // Keep last 10
        $_SESSION['cqrs_events'] = array_slice($events, 0, 10);

        return $this->renderPartial('backend_showcase.partials.cqrs_log', ['events' => $_SESSION['cqrs_events']]);
    }

    /* ------------------------------------------------------------------------
     * WORKFLOW SHOWCASE
     * ------------------------------------------------------------------------ */

    #[Route('/partial/workflow', method: 'GET')]
    public function partialWorkflow()
    {
        $state = $_SESSION['wf_state'] ?? 'draft';
        $history = $_SESSION['wf_history'] ?? [];
        
        $available = [];
        if ($state === 'draft') $available = ['submit'];
        if ($state === 'pending_review') $available = ['approve', 'reject'];
        if ($state === 'approved' || $state === 'rejected') $available = [];

        return $this->renderPartial('backend_showcase.partials.workflow', [
            'currentState' => $state,
            'availableTransitions' => $available,
            'history' => $history
        ]);
    }

    #[Route('/workflow/transition', method: 'POST')]
    public function applyTransition()
    {
        $transition = $_POST['transition'] ?? '';
        $state = $_SESSION['wf_state'] ?? 'draft';
        
        // Basic state machine logic mock
        $newState = $state;
        if ($state === 'draft' && $transition === 'submit') $newState = 'pending_review';
        if ($state === 'pending_review' && $transition === 'approve') $newState = 'approved';
        if ($state === 'pending_review' && $transition === 'reject') $newState = 'rejected';

        if ($newState !== $state) {
            $_SESSION['wf_state'] = $newState;
            $history = $_SESSION['wf_history'] ?? [];
            array_unshift($history, [
                'transition' => $transition,
                'state' => $newState,
                'time' => date('Y-m-d H:i:s')
            ]);
            $_SESSION['wf_history'] = $history;
        }

        // Return full workflow layout
        return $this->partialWorkflow();
    }

    #[Route('/workflow/reset', method: 'POST')]
    public function resetWorkflow()
    {
        $_SESSION['wf_state'] = 'draft';
        $_SESSION['wf_history'] = [];
        return $this->partialWorkflow();
    }

    /* ------------------------------------------------------------------------
     * ATTRIBUTE ROUTING SHOWCASE
     * ------------------------------------------------------------------------ */
    
    #[Route('/partial/routing', method: 'GET')]
    public function partialRouting()
    {
        return $this->renderPartial('backend_showcase.partials.routing');
    }

    #[Route('/routing/demo-auth', method: 'GET')]
    public function demoAuth()
    {
        return '<div style="padding: 1rem; background: rgba(52, 211, 153, 0.2); border-left: 4px solid #34d399; border-radius: 4px;">
            <strong>Success!</strong> Hit GET route properly processed via Attributes!
        </div>';
    }

    #[Route('/routing/demo-post', method: 'POST')]
    public function demoPost()
    {
        return '<div style="padding: 1rem; background: rgba(96, 165, 250, 0.2); border-left: 4px solid #60a5fa; border-radius: 4px;">
            <strong>Success!</strong> Hit POST route successfully. Notice no page reload!
        </div>';
    }

    /* ------------------------------------------------------------------------
     * SPPAI - UNIVERSAL AI GATEWAY SHOWCASE
     * ------------------------------------------------------------------------ */
    
    #[Route('/partial/sppai', method: 'GET')]
    public function partialSppai()
    {
        return $this->renderPartial('backend_showcase.partials.sppai');
    }

    #[Route('/sppai/prompt', method: 'POST')]
    public function sppaiPrompt()
    {
        $prompt = $_POST['prompt'] ?? '';
        
        if (empty($prompt)) {
            return '<div style="color: var(--danger); margin-top: 1rem;">Please enter a prompt.</div>';
        }

        try {
            if (class_exists('\SPPMod\SPPAI\SPPAI')) {
                // If the user has a provider configured (e.g. Ollama, OpenAI)
                $response = \SPPMod\SPPAI\SPPAI::complete($prompt);
                
                return '<div style="margin-top: 1rem; padding: 1.5rem; background: rgba(167, 139, 250, 0.1); border: 1px solid rgba(167, 139, 250, 0.3); border-radius: 8px;">
                    <div style="font-size: 0.8rem; color: #a78bfa; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em;">AI Response</div>
                    <div style="line-height: 1.6;">' . nl2br(htmlspecialchars($response)) . '</div>
                </div>';
            } else {
                throw new \Exception("SPPAI module not loaded.");
            }
        } catch (\Exception $e) {
            // Fallback for showcase purposes if no AI is configured
            return '<div style="margin-top: 1rem; padding: 1.5rem; background: rgba(167, 139, 250, 0.1); border: 1px solid rgba(167, 139, 250, 0.3); border-radius: 8px;">
                <div style="font-size: 0.8rem; color: #a78bfa; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em;">Mocked AI Response (No API Key Configured)</div>
                <div style="line-height: 1.6;">You asked: "<em>' . htmlspecialchars($prompt) . '</em>"<br><br>SPPAI is seamlessly routing this prompt. In a live environment with Ollama or OpenAI configured in <code>ai.yml</code>, you would see a streamed intelligent response here!</div>
            </div>';
        }
    }

    /* ------------------------------------------------------------------------
     * SPPQUEUE - DAG JOB ORCHESTRATOR SHOWCASE
     * ------------------------------------------------------------------------ */

    #[Route('/partial/queue', method: 'GET')]
    public function partialQueue()
    {
        // Reset simulation state
        $_SESSION['dag_simulation'] = [
            'ExtractData' => 'pending',
            'TransformData' => 'pending',
            'LoadData' => 'pending',
            'NotifyAdmin' => 'pending'
        ];
        return $this->renderPartial('backend_showcase.partials.queue');
    }

    #[Route('/queue/status', method: 'GET')]
    public function queueStatus()
    {
        $state = $_SESSION['dag_simulation'] ?? [];
        
        // Simulate DAG progression
        $updated = false;
        if ($state['ExtractData'] === 'pending') {
            $state['ExtractData'] = 'running';
            $updated = true;
        } elseif ($state['ExtractData'] === 'running') {
            $state['ExtractData'] = 'completed';
            $state['TransformData'] = 'running';
            $updated = true;
        } elseif ($state['TransformData'] === 'running') {
            $state['TransformData'] = 'completed';
            $state['LoadData'] = 'running';
            $updated = true;
        } elseif ($state['LoadData'] === 'running') {
            $state['LoadData'] = 'completed';
            $state['NotifyAdmin'] = 'running';
            $updated = true;
        } elseif ($state['NotifyAdmin'] === 'running') {
            $state['NotifyAdmin'] = 'completed';
            $updated = true;
        }
        
        $_SESSION['dag_simulation'] = $state;

        return $this->renderPartial('backend_showcase.partials.queue_status', ['state' => $state]);
    }

    /* ------------------------------------------------------------------------
     * SPPPOLYGLOT - MICROSERVICES BRIDGE SHOWCASE
     * ------------------------------------------------------------------------ */

    #[Route('/partial/polyglot', method: 'GET')]
    public function partialPolyglot()
    {
        return $this->renderPartial('backend_showcase.partials.polyglot');
    }

    #[Route('/polyglot/execute', method: 'POST')]
    public function executePolyglot()
    {
        $lang = $_POST['lang'] ?? 'py';
        $scripts = [
            'py' => 'hello.py',
            'js' => 'hello.js',
            'go' => 'hello.go',
            'java' => 'Hello.java',
            'cpp' => 'hello.cpp',
            'cs' => 'Hello.cs',
            'pl' => 'hello.pl'
        ];

        if (!isset($scripts[$lang])) {
            return '<div style="color: var(--danger);">Unsupported language selected.</div>';
        }

        $scriptName = $scripts[$lang];
        $fullPath = SPP_APP_DIR . "/src/Samvaad/services/polyglot/{$scriptName}";
        
        if (!file_exists($fullPath)) {
            return '<div style="color: var(--danger);">Script not found: ' . htmlspecialchars($scriptName) . '</div>';
        }

        $arg = escapeshellarg(json_encode(['name' => 'SPP User', 'timestamp' => time()]));
        
        $interpreters = [
            'py' => 'python3',
            'js' => 'node',
            'pl' => 'perl',
            'go' => 'go run',
        ];

        try {
            if ($lang === 'java') {
                // Java needs compilation first ideally, but Java 11+ supports single-file run
                $cmd = escapeshellcmd("java") . " " . escapeshellarg($fullPath) . " " . $arg;
            } elseif ($lang === 'cpp') {
                // C++ needs compilation
                $binPath = SPP_APP_DIR . "/src/Samvaad/services/polyglot/hello_cpp.out";
                if (!file_exists($binPath)) {
                    exec("g++ " . escapeshellarg($fullPath) . " -o " . escapeshellarg($binPath));
                }
                $cmd = escapeshellarg($binPath) . " " . $arg;
            } elseif ($lang === 'cs') {
                // DotNet needs `dotnet run` but we have a raw Hello.cs script. 
                // Let's mock dotnet execution since scaffolding a full C# project dynamically is out of scope.
                $mockOutput = json_encode(['greeting' => 'Hello SPP User from C#!', 'lang' => 'C#', 'status' => 'success']);
                return '<pre>' . htmlspecialchars(json_encode(json_decode($mockOutput), JSON_PRETTY_PRINT)) . '</pre>';
            } else {
                $interpreter = $interpreters[$lang] ?? 'python3';
                $cmd = escapeshellcmd($interpreter) . " " . escapeshellarg($fullPath) . " " . $arg;
            }

            // Using SPP Polyglot logic
            $output = shell_exec($cmd);
            
            if (!$output) {
                return '<div style="color: var(--danger);">No output from script or execution failed.</div>';
            }

            // Try to parse JSON output
            $decoded = json_decode($output, true);
            if ($decoded) {
                return '<pre>' . htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT)) . '</pre>';
            }
            return '<pre>' . htmlspecialchars($output) . '</pre>';
            
        } catch (\Exception $e) {
            return '<div style="color: var(--danger);">Execution Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}
