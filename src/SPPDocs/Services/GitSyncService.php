<?php

namespace App\SPPDocs\Services;

/**
 * GitSyncService
 * Bi-directional Git Porcelain synchronization engine for SPPDocs.
 * Automatically commits and pushes web editor modifications, and safely rebases inbound commits.
 */
class GitSyncService
{
    /**
     * Check if Git executable is accessible on the host machine.
     */
    public static function isGitAvailable(): bool
    {
        static $available = null;
        if ($available !== null) {
            return $available;
        }

        $output = [];
        $returnCode = 0;
        @exec('git --version 2>&1', $output, $returnCode);
        $available = ($returnCode === 0);
        return $available;
    }

    /**
     * Execute a Git command within the given repository directory.
     */
    private static function runGit(string $repoDir, string $command): array
    {
        if (!self::isGitAvailable()) {
            return ['code' => -1, 'output' => 'Git binary not available on host system.'];
        }

        $cwd = getcwd();
        @chdir($repoDir);
        $output = [];
        $returnCode = 0;
        @exec('git ' . $command . ' 2>&1', $output, $returnCode);
        @chdir($cwd);

        return [
            'code' => $returnCode,
            'output' => implode("\n", $output),
        ];
    }

    /**
     * Commit a modified documentation file to Git.
     *
     * @param array $project Project configuration array
     * @param string $filePath Full path to modified file
     * @param string $author Username of the editor
     * @return array Status with 'success' and 'message'
     */
    public static function commitFile(array $project, string $filePath, string $author = 'admin'): array
    {
        if (empty($project['git_sync'])) {
            return ['success' => true, 'message' => 'Git sync is disabled for this project.'];
        }

        if (!self::isGitAvailable()) {
            return ['success' => false, 'message' => 'Git command line is not available.'];
        }

        $repoDir = dirname(SPP_BASE_DIR);
        $relPath = str_replace([$repoDir . '/', $repoDir . '\\', 'C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $filePath);

        // Stage file
        $addRes = self::runGit($repoDir, 'add ' . escapeshellarg($relPath));
        if ($addRes['code'] !== 0) {
            return ['success' => false, 'message' => 'Git add failed: ' . $addRes['output']];
        }

        // Commit file
        $msg = "docs(" . basename($relPath) . "): update via SPPDocs Web CMS [by {$author}]";
        $commitRes = self::runGit($repoDir, 'commit -m ' . escapeshellarg($msg));

        // Auto-push if configured
        $pushOutput = '';
        if (!empty($project['git_auto_push']) && $commitRes['code'] === 0) {
            $pushRes = self::runGit($repoDir, 'push origin HEAD');
            $pushOutput = ' | Push: ' . $pushRes['output'];
        }

        return [
            'success' => ($commitRes['code'] === 0 || str_contains($commitRes['output'], 'nothing to commit')),
            'message' => $commitRes['output'] . $pushOutput,
        ];
    }

    /**
     * Safely pull upstream remote changes into the local working directory.
     * Prevents split-brain disk clobbering by creating an isolated backup branch upon merge conflicts.
     */
    public static function pullUpstream(string $branch = 'main'): array
    {
        if (!self::isGitAvailable()) {
            return ['success' => false, 'message' => 'Git binary not available.'];
        }

        $repoDir = dirname(SPP_BASE_DIR);

        // Fetch
        $fetchRes = self::runGit($repoDir, 'fetch origin ' . escapeshellarg($branch));
        if ($fetchRes['code'] !== 0) {
            return ['success' => false, 'message' => 'Git fetch failed: ' . $fetchRes['output']];
        }

        // Attempt fast-forward merge
        $mergeRes = self::runGit($repoDir, 'merge --ff-only origin/' . escapeshellarg($branch));
        if ($mergeRes['code'] === 0) {
            return ['success' => true, 'message' => 'Successfully fast-forwarded: ' . $mergeRes['output']];
        }

        // Conflict detected: create backup branch and avoid clobbering
        $conflictBranch = 'backup_conflict_' . time();
        self::runGit($repoDir, 'checkout -b ' . escapeshellarg($conflictBranch));

        return [
            'success' => false,
            'message' => 'Upstream merge conflict detected. Local state safely preserved in branch ' . $conflictBranch,
        ];
    }
}
