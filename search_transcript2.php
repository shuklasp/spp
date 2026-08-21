<?php
$transcript = 'c:\Users\Satya Prakash Shukla\.gemini\antigravity\brain\01022eab-50c9-41e2-a061-69ab983d90e0\.system_generated\logs\transcript_full.jsonl';
$handle = fopen($transcript, "r");
if ($handle) {
    while (($line = fgets($handle)) !== false) {
        if (strpos($line, 'DevLegacyCommand.php') !== false) {
            $data = json_decode($line, true);
            if (isset($data['content'])) {
                if (strlen($data['content']) > 1000) {
                    file_put_contents("DevLegacyCommand_backup.txt", $data['content']);
                    echo "Found large content block, saved to DevLegacyCommand_backup.txt\n";
                }
            }
        }
    }
    fclose($handle);
}
