<?php
$transcript = 'c:\Users\Satya Prakash Shukla\.gemini\antigravity\brain\01022eab-50c9-41e2-a061-69ab983d90e0\.system_generated\logs\transcript_full.jsonl';
$handle = fopen($transcript, "r");
if ($handle) {
    while (($line = fgets($handle)) !== false) {
        if (strpos($line, 'spp/dev/services') !== false) {
            $data = json_decode($line, true);
            if (isset($data['tool_calls'])) {
                foreach ($data['tool_calls'] as $call) {
                    if ($call['function']['name'] == 'default_api:run_command') {
                        echo "run_command: " . $call['function']['arguments'] . "\n";
                    }
                }
            }
            if (isset($data['content']) && strpos($data['content'], 'spp/dev/services') !== false) {
                echo "Content Match: " . substr($data['content'], 0, 500) . "\n...\n";
            }
        }
    }
    fclose($handle);
}
