<?php

namespace App\SPPDocs\Services;

/**
 * GitAutomationService
 * Parses commit messages, pull requests, and webhooks to automate issue lifecycle,
 * time tracking, and CI/CD workflow generation.
 */
class GitAutomationService
{
    /**
     * Parse commit message for automated actions.
     */
    public static function parseCommitMessage(string $message): array
    {
        $actions = [
            'close_issues' => [],
            'ref_issues' => [],
            'time_logs' => [],
        ];

        // 1. Fixes / Closes / Resolves
        if (preg_match_all('/(fix(?:es|ed)?|close[sd]?|resolve[sd]?)\s+#([a-zA-Z0-9_]+)/i', $message, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $actions['close_issues'][] = $m[2];
            }
        }

        // 2. References / Mentions
        if (preg_match_all('/(ref(?:s)?|see|re)\s+#([a-zA-Z0-9_]+)/i', $message, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $actions['ref_issues'][] = $m[2];
            }
        }

        // 3. Time Tracking: "time: #12 2.5h" or "log #12 45m" or "Time: 2.5h" with Fixes #id
        if (preg_match_all('/(?:log|time|progress):?\s+#([a-zA-Z0-9_]+)\s+([0-9\.]+)(h|m)(?:\s+"([^"]+)")?/i', $message, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $val = (float)$m[2];
                $unit = strtolower($m[3]);
                $hours = ($unit === 'm') ? round($val / 60, 2) : $val;
                $actions['time_logs'][] = [
                    'issue' => $m[1],
                    'hours' => $hours,
                    'note' => $m[4] ?? ''
                ];
            }
        } elseif (!empty($actions['close_issues']) && preg_match('/(?:time|spent):?\s+([0-9\.]+)(h|m)(?:\s+"([^"]+)")?/i', $message, $m)) {
            $val = (float)$m[1];
            $unit = strtolower($m[2]);
            $hours = ($unit === 'm') ? round($val / 60, 2) : $val;
            $actions['time_logs'][] = [
                'issue' => $actions['close_issues'][0],
                'hours' => $hours,
                'note' => $m[3] ?? ''
            ];
        }

        return $actions;
    }

    /**
     * Generate GitHub Actions workflow YAML.
     */
    public static function generateGitHubActionsWorkflow(string $projectId, string $webhookUrl, string $secret = ''): string
    {
        return <<<YML
name: SPPDocs Continuous Integration & Sync

on:
  push:
    branches: [ main, master, develop ]
  pull_request:
    types: [ opened, closed, reopened, synchronize ]

jobs:
  sppdocs-sync:
    name: Notify SPPDocs Hub
    runs-on: ubuntu-latest
    steps:
      - name: Checkout Source Code
        uses: actions/checkout@v4
        with:
          fetch-depth: 5

      - name: Dispatch Git Webhook to SPPDocs Hub
        run: |
          curl -X POST "{$webhookUrl}" \\
            -H "Content-Type: application/json" \\
            -H "X-GitHub-Event: \${{ github.event_name }}" \\
            -H "X-Hub-Signature-256: \${{ secrets.SPPDOCS_WEBHOOK_SECRET || '{$secret}' }}" \\
            -d '\${{ toJson(github.event) }}'
YML;
    }

    /**
     * Generate GitLab CI configuration YAML.
     */
    public static function generateGitLabCiConfig(string $projectId, string $webhookUrl, string $secret = ''): string
    {
        return <<<YML
stages:
  - notify

sppdocs_webhook:
  stage: notify
  image: curlimages/curl:latest
  script:
    - >
      curl -X POST "{$webhookUrl}"
      -H "Content-Type: application/json"
      -H "X-Gitlab-Event: Push Hook"
      -H "X-Gitlab-Token: \${SPPDOCS_WEBHOOK_TOKEN:-{$secret}}"
      -d "{\"project_id\": \"{$projectId}\", \"ref\": \"\$CI_COMMIT_REF_NAME\", \"commits\": [{\"id\": \"\$CI_COMMIT_SHA\", \"message\": \"\$CI_COMMIT_MESSAGE\", \"author\": {\"name\": \"\$GITLAB_USER_NAME\"}}]}"
  only:
    - main
    - master
YML;
    }

    /**
     * Generate Git post-commit hook for local automatic synchronization.
     */
    public static function generateLocalGitHook(string $projectId, string $webhookUrl): string
    {
        return <<<BASH
#!/bin/sh
# SPPDocs Local Post-Commit Hook
# Automatically notifies the local SPPDocs instance of commit metadata

COMMIT_MSG=\$(git log -1 --pretty=%B)
COMMIT_HASH=\$(git log -1 --pretty=%H)
AUTHOR_NAME=\$(git log -1 --pretty=%an)

curl -s -X POST "{$webhookUrl}" \\
  -H "Content-Type: application/json" \\
  -H "X-GitHub-Event: push" \\
  -d "{\"project_id\": \"{$projectId}\", \"commits\": [{\"id\": \"\$COMMIT_HASH\", \"message\": \"\$COMMIT_MSG\", \"author\": {\"name\": \"\$AUTHOR_NAME\"}}]}" > /dev/null 2>&1 &
BASH;
    }
}
