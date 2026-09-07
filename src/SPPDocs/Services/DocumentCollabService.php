<?php

namespace App\SPPDocs\Services;

/**
 * DocumentCollabService
 * Real-time presence tracking, multi-cursor broadcasting, section-level lease locks,
 * and 3-way automated line diff merging.
 */
class DocumentCollabService
{
    private const LEASE_TTL = 35; // seconds before a peer presence or lock expires

    /**
     * Record a heartbeat from an active editor or viewer.
     */
    public static function heartbeat(string $projectId, string $pageSlug, string $username, string $status = 'editing', ?array $cursor = null): array
    {
        $dir = self::getCollabDir($projectId);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $filePath = self::getCollabFile($projectId, $pageSlug);
        $data = file_exists($filePath) ? (json_decode(file_get_contents($filePath), true) ?: []) : [];

        $now = time();
        $users = $data['users'] ?? [];
        $sectionLocks = $data['section_locks'] ?? [];

        // Purge expired users
        foreach ($users as $u => $info) {
            if (($now - ($info['last_seen'] ?? 0)) > self::LEASE_TTL) {
                unset($users[$u]);
            }
        }

        // Purge expired section locks
        foreach ($sectionLocks as $sId => $lockInfo) {
            if ($now > ($lockInfo['expires_at'] ?? 0)) {
                unset($sectionLocks[$sId]);
            }
        }

        // Upsert current user presence
        $avatarColors = ['#ea580c', '#3b82f6', '#10b981', '#8b5cf6', '#ec4899', '#f59e0b', '#06b6d4', '#14b8a6', '#6366f1'];
        $hash = crc32($username);
        $color = $avatarColors[abs($hash) % count($avatarColors)];

        $userInfo = [
            'username' => $username,
            'avatar' => strtoupper(substr($username, 0, 1)),
            'color' => $color,
            'status' => $status,
            'last_seen' => $now,
            'cursor' => $cursor ?: ($users[$username]['cursor'] ?? null),
        ];
        $users[$username] = $userInfo;

        // Manage overall document soft edit lock
        $docLock = $data['lock'] ?? null;
        if ($docLock && (($now > ($docLock['expires_at'] ?? 0)) || $docLock['holder'] === $username)) {
            $docLock = null;
        }

        if (!$docLock && $status === 'editing') {
            $docLock = [
                'holder' => $username,
                'acquired_at' => $now,
                'expires_at' => $now + self::LEASE_TTL,
            ];
        } elseif ($docLock && $docLock['holder'] === $username) {
            $docLock['expires_at'] = $now + self::LEASE_TTL;
        }

        $data['users'] = $users;
        $data['lock'] = $docLock;
        $data['section_locks'] = $sectionLocks;

        @file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);

        // Format active peers (excluding current user)
        $peers = [];
        foreach ($users as $u => $info) {
            if ($u !== $username) {
                $peers[] = $info;
            }
        }

        return [
            'success' => true,
            'user' => $userInfo,
            'peers' => $peers,
            'peer_count' => count($peers),
            'lock_holder' => $docLock['holder'] ?? null,
            'is_locked_by_other' => ($docLock && $docLock['holder'] !== $username),
            'section_locks' => $sectionLocks,
        ];
    }

    /**
     * Acquire a soft lock on a specific section/heading ID.
     */
    public static function acquireSectionLock(string $projectId, string $pageSlug, string $sectionId, string $username): array
    {
        $filePath = self::getCollabFile($projectId, $pageSlug);
        $data = file_exists($filePath) ? (json_decode(file_get_contents($filePath), true) ?: []) : [];

        $now = time();
        $sectionLocks = $data['section_locks'] ?? [];

        // Check existing lock
        if (isset($sectionLocks[$sectionId])) {
            $currentLock = $sectionLocks[$sectionId];
            if ($now <= ($currentLock['expires_at'] ?? 0) && $currentLock['holder'] !== $username) {
                return [
                    'success' => false,
                    'error' => 'Section is locked by ' . $currentLock['holder'],
                    'lock' => $currentLock,
                ];
            }
        }

        // Grant lock
        $sectionLocks[$sectionId] = [
            'section_id' => $sectionId,
            'holder' => $username,
            'acquired_at' => $now,
            'expires_at' => $now + self::LEASE_TTL,
        ];

        $data['section_locks'] = $sectionLocks;
        @file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);

        return [
            'success' => true,
            'section_id' => $sectionId,
            'lock' => $sectionLocks[$sectionId],
        ];
    }

    /**
     * Release a soft lock on a specific section/heading ID.
     */
    public static function releaseSectionLock(string $projectId, string $pageSlug, string $sectionId, string $username): array
    {
        $filePath = self::getCollabFile($projectId, $pageSlug);
        if (!file_exists($filePath)) {
            return ['success' => true];
        }

        $data = json_decode(file_get_contents($filePath), true) ?: [];
        if (isset($data['section_locks'][$sectionId])) {
            if ($data['section_locks'][$sectionId]['holder'] === $username) {
                unset($data['section_locks'][$sectionId]);
                @file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
            }
        }

        return ['success' => true];
    }

    /**
     * Get live collab state for SSE stream without mutating presence.
     */
    public static function getState(string $projectId, string $pageSlug, string $currentUser): array
    {
        $filePath = self::getCollabFile($projectId, $pageSlug);
        if (!file_exists($filePath)) {
            return [
                'peers' => [],
                'peer_count' => 0,
                'lock_holder' => null,
                'is_locked_by_other' => false,
                'section_locks' => [],
            ];
        }

        $data = json_decode(file_get_contents($filePath), true) ?: [];
        $now = time();

        $peers = [];
        foreach (($data['users'] ?? []) as $u => $info) {
            if (($now - ($info['last_seen'] ?? 0)) <= self::LEASE_TTL && $u !== $currentUser) {
                $peers[] = $info;
            }
        }

        $sectionLocks = [];
        foreach (($data['section_locks'] ?? []) as $sId => $lock) {
            if ($now <= ($lock['expires_at'] ?? 0)) {
                $sectionLocks[$sId] = $lock;
            }
        }

        $docLock = $data['lock'] ?? null;
        $isLocked = ($docLock && $now <= ($docLock['expires_at'] ?? 0) && $docLock['holder'] !== $currentUser);

        return [
            'peers' => $peers,
            'peer_count' => count($peers),
            'lock_holder' => $docLock['holder'] ?? null,
            'is_locked_by_other' => $isLocked,
            'section_locks' => $sectionLocks,
        ];
    }

    /**
     * Clear user presence when exiting the editor.
     */
    public static function leave(string $projectId, string $pageSlug, string $username): void
    {
        $filePath = self::getCollabFile($projectId, $pageSlug);
        if (!file_exists($filePath)) return;

        $data = json_decode(file_get_contents($filePath), true) ?: [];
        if (isset($data['users'][$username])) {
            unset($data['users'][$username]);
        }
        if (isset($data['lock']['holder']) && $data['lock']['holder'] === $username) {
            unset($data['lock']);
        }
        if (!empty($data['section_locks'])) {
            foreach ($data['section_locks'] as $sId => $l) {
                if (($l['holder'] ?? '') === $username) {
                    unset($data['section_locks'][$sId]);
                }
            }
        }

        @file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * Execute a 3-way line-based diff merge.
     */
    public static function diffMerge(string $base, string $theirs, string $mine): array
    {
        // If local didn't change from base, accept server version
        if ($mine === $base) {
            return [
                'has_conflicts' => false,
                'conflict_count' => 0,
                'merged' => $theirs,
                'status' => 'clean_theirs',
            ];
        }

        // If server didn't change from base, accept local version
        if ($theirs === $base) {
            return [
                'has_conflicts' => false,
                'conflict_count' => 0,
                'merged' => $mine,
                'status' => 'clean_mine',
            ];
        }

        // If both made identical changes
        if ($mine === $theirs) {
            return [
                'has_conflicts' => false,
                'conflict_count' => 0,
                'merged' => $mine,
                'status' => 'identical',
            ];
        }

        $baseLines = explode("\n", str_replace("\r\n", "\n", $base));
        $theirsLines = explode("\n", str_replace("\r\n", "\n", $theirs));
        $mineLines = explode("\n", str_replace("\r\n", "\n", $mine));

        // Use diff3 style hunk alignment
        $conflicts = 0;
        $mergedLines = [];
        $bIdx = 0;
        $tIdx = 0;
        $mIdx = 0;

        while ($bIdx < count($baseLines) || $tIdx < count($theirsLines) || $mIdx < count($mineLines)) {
            $b = $baseLines[$bIdx] ?? null;
            $t = $theirsLines[$tIdx] ?? null;
            $m = $mineLines[$mIdx] ?? null;

            // All match
            if ($b === $t && $t === $m) {
                if ($m !== null) $mergedLines[] = $m;
                $bIdx++; $tIdx++; $mIdx++;
                continue;
            }

            // Only Mine changed from Base, Theirs stayed same as Base
            if ($b === $t && $b !== $m) {
                if ($m !== null) $mergedLines[] = $m;
                $bIdx++; $tIdx++; $mIdx++;
                continue;
            }

            // Only Theirs changed from Base, Mine stayed same as Base
            if ($b === $m && $b !== $t) {
                if ($t !== null) $mergedLines[] = $t;
                $bIdx++; $tIdx++; $mIdx++;
                continue;
            }

            // Both changed identically
            if ($t === $m) {
                if ($m !== null) $mergedLines[] = $m;
                $bIdx++; $tIdx++; $mIdx++;
                continue;
            }

            // Both changed differently: CONFLICT HUNK
            $conflicts++;
            $mergedLines[] = "<<<<<<< LOCAL EDITS (YOU)";
            if ($m !== null) $mergedLines[] = $m;
            $mergedLines[] = "=======";
            if ($t !== null) $mergedLines[] = $t;
            $mergedLines[] = ">>>>>>> SERVER VERSION (REMOTE)";

            $bIdx++; $tIdx++; $mIdx++;
        }

        return [
            'has_conflicts' => ($conflicts > 0),
            'conflict_count' => $conflicts,
            'merged' => implode("\n", $mergedLines),
            'status' => ($conflicts > 0) ? 'conflict' : 'clean_merge',
        ];
    }

    private static function getCollabDir(string $projectId): string
    {
        $issuesDir = PermissionManager::resolveIssuesDir($projectId) ?: (dirname(SPP_BASE_DIR) . '/docs/' . $projectId . '/issues');
        return $issuesDir . '/collab';
    }

    private static function getCollabFile(string $projectId, string $pageSlug): string
    {
        $safeSlug = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $pageSlug);
        return self::getCollabDir($projectId) . '/' . $safeSlug . '.json';
    }
}