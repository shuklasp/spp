<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\PolyglotBridge;

/**
 * Class AskCommand
 * Connects to the Polyglot AI Mentor to provide AI-driven onboarding.
 * Includes a graceful degradation fallback to full-text search.
 */
class AskCommand extends Command
{
    protected string $name = 'ask';
    protected string $description = 'Ask the SPP AI Mentor a question about the framework.';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        if (empty($this->getArgument($args, 0))) {
            echo "Usage: php spp.php ask \"How do I do X?\"\n";
            return;
        }

        $question = implode(" ", $args);
        echo "🤔 Thinking: " . $question . "\n";
        echo "--------------------------------------------------------\n";

        try {
            // Attempt to contact the AI Daemon via the Polyglot Bridge
            // Signature: call(string $lang, string $module, string $func, array $args = [], bool $daemon = false)
            $response = PolyglotBridge::call('python', 'services/python/ai_mentor.py', 'handle_spp_request', [
                [
                    'action'   => 'ask',
                    'question' => $question
                ]
            ], true);

            if (isset($response['error'])) {
                throw new \Exception($response['error']);
            }

            echo "\n🤖 AI MENTOR SAYS:\n";
            echo $response['answer'] ?? "No answer received.";
            echo "\n--------------------------------------------------------\n";
            
        } catch (\Throwable $e) {
            echo "\n⚠️  [AI Mentor Unavailable]: " . $e->getMessage() . "\n";
            echo "🔄 Falling back to Graceful Degradation (Local Documentation Search)...\n\n";
            $this->fallbackSearch($question);
        }
    }

    /**
     * Graceful Degradation: Crawls documentation/ and man/ directories for keywords.
     */
    private function fallbackSearch(string $question): void
    {
        $docsDir = SPP_APP_DIR . '/documentation';
        if (!is_dir($docsDir)) {
            echo "Error: Documentation directory not found at $docsDir\n";
            return;
        }

        // Extremely naive keyword extraction
        $stopWords = ['how', 'do', 'i', 'what', 'is', 'the', 'a', 'to', 'make', 'create', 'an'];
        $words = explode(" ", strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $question)));
        $keywords = array_diff($words, $stopWords);

        if (empty($keywords)) {
            echo "Could not extract searchable keywords from your question.\n";
            return;
        }

        echo "🔍 Searching local manuals for keywords: " . implode(", ", $keywords) . "\n\n";

        $results = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($docsDir));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $content = file_get_contents($file->getPathname());
                $contentLower = strtolower($content);
                
                $score = 0;
                foreach ($keywords as $kw) {
                    if (str_contains($contentLower, $kw)) {
                        $score++;
                    }
                }

                if ($score > 0) {
                    $results[] = [
                        'file'  => str_replace(SPP_APP_DIR, '', $file->getPathname()),
                        'score' => $score
                    ];
                }
            }
        }

        // Sort by highest score
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        
        $topResults = array_slice($results, 0, 3);
        if (empty($topResults)) {
            echo "No manual pages matched your query. Try rephrasing or checking the 'rosetta-stone.md'!\n";
            return;
        }

        echo "📚 TOP MANUAL PAGES:\n";
        foreach ($topResults as $idx => $res) {
            echo "  " . ($idx + 1) . ". " . $res['file'] . " (Matches: " . $res['score'] . ")\n";
        }
        
        echo "\n💡 Tip: Open these files to find the answer manually.\n";
    }
}
