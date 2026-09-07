<?php

namespace App\SPPDocs\Services;

use PDO;
use Exception;

/**
 * LmsLedgerService
 * High-Performance SQLite Caching Ledger for SPPDocs LMS.
 * 
 * Provides sub-millisecond O(1) analytics, instant class-wide score aggregations,
 * and high-speed CSV gradebook streaming while preserving 100% Git-porcelain
 * human-readable JSON files in .progress/ and .certificates/.
 */
class LmsLedgerService
{
    private static array $pdoInstances = [];

    /**
     * Alias for getDatabase()
     */
    public static function getDb(string $projectId, ?array $project = null): ?PDO
    {
        return self::getDatabase($projectId, $project);
    }

    /**
     * Resolve or initialize the SQLite PDO instance for a given project.
     */
    public static function getDatabase(string $projectId, ?array $project = null): ?PDO
    {
        if (isset(self::$pdoInstances[$projectId])) {
            return self::$pdoInstances[$projectId];
        }

        if (!$project) {
            $project = PermissionManager::loadProjectConfig($projectId) ?: [];
        }

        $coursesDir = LmsService::getCoursesDir($project);
        $progressDir = $coursesDir . '/.progress';
        if (!is_dir($progressDir)) {
            @mkdir($progressDir, 0777, true);
        }

        $dbPath = $progressDir . '/ledger.sqlite3';

        try {
            $pdo = new PDO('sqlite:' . $dbPath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
            ]);

            // High-concurrency WAL mode & performance pragmas
            $pdo->exec('PRAGMA journal_mode = WAL;');
            $pdo->exec('PRAGMA synchronous = NORMAL;');
            $pdo->exec('PRAGMA temp_store = MEMORY;');
            $pdo->exec('PRAGMA busy_timeout = 5000;');

            self::initializeSchema($pdo);
            self::$pdoInstances[$projectId] = $pdo;
            return $pdo;
        } catch (Exception $e) {
            error_log('LmsLedgerService initialization error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create ledger tables and indices if they do not already exist.
     */
    private static function initializeSchema(PDO $pdo): void
    {
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS lms_progress (
                project_id TEXT NOT NULL,
                course_id TEXT NOT NULL,
                username TEXT NOT NULL,
                state TEXT NOT NULL DEFAULT "enrolled",
                percentage INTEGER NOT NULL DEFAULT 0,
                completed_count INTEGER NOT NULL DEFAULT 0,
                total_items INTEGER NOT NULL DEFAULT 0,
                certificate_id TEXT,
                last_accessed_at INTEGER NOT NULL,
                enrolled_at INTEGER NOT NULL,
                updated_at INTEGER NOT NULL,
                PRIMARY KEY (project_id, course_id, username)
            );

            CREATE INDEX IF NOT EXISTS idx_lms_progress_course 
                ON lms_progress (project_id, course_id, percentage);

            CREATE TABLE IF NOT EXISTS lms_quiz_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id TEXT NOT NULL,
                course_id TEXT NOT NULL,
                lesson_id TEXT NOT NULL,
                username TEXT NOT NULL,
                score INTEGER NOT NULL,
                passed INTEGER NOT NULL,
                attempt_number INTEGER NOT NULL DEFAULT 1,
                created_at INTEGER NOT NULL
            );

            CREATE INDEX IF NOT EXISTS idx_lms_attempts_query 
                ON lms_quiz_attempts (project_id, course_id, username);

            CREATE TABLE IF NOT EXISTS lms_certificates (
                cert_code TEXT PRIMARY KEY,
                project_id TEXT NOT NULL,
                course_id TEXT NOT NULL,
                course_title TEXT NOT NULL,
                username TEXT NOT NULL,
                student_name TEXT NOT NULL,
                score INTEGER NOT NULL DEFAULT 100,
                verification_hash TEXT,
                issued_at INTEGER NOT NULL
            );

            CREATE INDEX IF NOT EXISTS idx_lms_certs_course 
                ON lms_certificates (project_id, course_id);
        ');
    }

    /**
     * Write-through progress upsert called whenever student progress changes.
     */
    public static function recordProgress(string $projectId, array $progress, ?array $project = null): void
    {
        $pdo = self::getDatabase($projectId, $project);
        if (!$pdo) {
            return;
        }

        $now = time();
        $stmt = $pdo->prepare('
            INSERT INTO lms_progress (
                project_id, course_id, username, state, percentage,
                completed_count, total_items, certificate_id,
                last_accessed_at, enrolled_at, updated_at
            ) VALUES (
                :project_id, :course_id, :username, :state, :percentage,
                :completed_count, :total_items, :certificate_id,
                :last_accessed_at, :enrolled_at, :updated_at
            )
            ON CONFLICT(project_id, course_id, username) DO UPDATE SET
                state = excluded.state,
                percentage = excluded.percentage,
                completed_count = excluded.completed_count,
                total_items = excluded.total_items,
                certificate_id = COALESCE(excluded.certificate_id, lms_progress.certificate_id),
                last_accessed_at = excluded.last_accessed_at,
                updated_at = excluded.updated_at
        ');

        $stmt->execute([
            ':project_id' => $projectId,
            ':course_id' => $progress['course_id'] ?? '',
            ':username' => $progress['username'] ?? '',
            ':state' => $progress['state'] ?? 'in_progress',
            ':percentage' => (int)($progress['percentage'] ?? 0),
            ':completed_count' => (int)($progress['completed_count'] ?? 0),
            ':total_items' => (int)($progress['total_items'] ?? 0),
            ':certificate_id' => $progress['certificate_id'] ?? null,
            ':last_accessed_at' => (int)($progress['last_accessed_at'] ?? $now),
            ':enrolled_at' => (int)($progress['enrolled_at'] ?? $now),
            ':updated_at' => $now,
        ]);
    }

    /**
     * Record a quiz attempt in the ledger.
     */
    public static function recordAttempt(
        string $projectId,
        string $courseId,
        string $lessonId,
        string $username,
        int $score,
        bool $passed,
        int $attemptNumber = 1,
        ?array $project = null
    ): void {
        $pdo = self::getDatabase($projectId, $project);
        if (!$pdo) {
            return;
        }

        $stmt = $pdo->prepare('
            INSERT INTO lms_quiz_attempts (
                project_id, course_id, lesson_id, username,
                score, passed, attempt_number, created_at
            ) VALUES (
                :project_id, :course_id, :lesson_id, :username,
                :score, :passed, :attempt_number, :created_at
            )
        ');

        $stmt->execute([
            ':project_id' => $projectId,
            ':course_id' => $courseId,
            ':lesson_id' => $lessonId,
            ':username' => $username,
            ':score' => $score,
            ':passed' => $passed ? 1 : 0,
            ':attempt_number' => $attemptNumber,
            ':created_at' => time(),
        ]);
    }

    /**
     * Record a certificate in the ledger.
     */
    public static function recordCertificate(string $projectId, array $certData, ?array $project = null): void
    {
        $pdo = self::getDatabase($projectId, $project);
        if (!$pdo) {
            return;
        }

        $stmt = $pdo->prepare('
            INSERT INTO lms_certificates (
                cert_code, project_id, course_id, course_title,
                username, student_name, score, verification_hash, issued_at
            ) VALUES (
                :cert_code, :project_id, :course_id, :course_title,
                :username, :student_name, :score, :verification_hash, :issued_at
            )
            ON CONFLICT(cert_code) DO UPDATE SET
                student_name = excluded.student_name,
                verification_hash = excluded.verification_hash,
                issued_at = excluded.issued_at
        ');

        $stmt->execute([
            ':cert_code' => $certData['certificate_id'] ?? $certData['code'] ?? '',
            ':project_id' => $projectId,
            ':course_id' => $certData['course_id'] ?? '',
            ':course_title' => $certData['course_title'] ?? '',
            ':username' => $certData['username'] ?? '',
            ':student_name' => $certData['student_name'] ?? $certData['username'] ?? '',
            ':score' => (int)($certData['score'] ?? 100),
            ':verification_hash' => $certData['verification_hash'] ?? '',
            ':issued_at' => (int)($certData['issued_at'] ?? time()),
        ]);
    }

    /**
     * Fluent helper to sync progress data structure into SQLite ledger.
     */
    public static function syncProgress(string $projectId, string $courseId, string $username, array $progressData, ?array $project = null): void
    {
        $payload = array_merge($progressData, [
            'course_id' => $courseId,
            'username' => $username,
            'completed_count' => count($progressData['completed_lessons'] ?? []),
            'total_items' => count($progressData['completed_lessons'] ?? []) ?: 1,
        ]);
        self::recordProgress($projectId, $payload, $project);
    }

    /**
     * Fluent helper to sync certificate data structure into SQLite ledger.
     */
    public static function syncCertificate(string $projectId, string $code, array $certData, ?array $project = null): void
    {
        $certData['code'] = $code;
        $certData['certificate_id'] = $code;
        self::recordCertificate($projectId, $certData, $project);
    }

    /**
     * Fast O(1) aggregate query for course analytics.
     */
    public static function getAggregatedAnalytics(string $projectId, string $courseId, ?array $project = null): ?array
    {
        $pdo = self::getDatabase($projectId, $project);
        if (!$pdo) {
            return null;
        }

        // 1. Progress aggregates
        $stmt = $pdo->prepare('
            SELECT 
                COUNT(*) as total_students,
                SUM(CASE WHEN percentage >= 100 THEN 1 ELSE 0 END) as completed_students,
                SUM(CASE WHEN percentage < 100 THEN 1 ELSE 0 END) as in_progress_students
            FROM lms_progress 
            WHERE project_id = :project_id AND course_id = :course_id
        ');
        $stmt->execute([':project_id' => $projectId, ':course_id' => $courseId]);
        $counts = $stmt->fetch() ?: ['total_students' => 0, 'completed_students' => 0, 'in_progress_students' => 0];

        // 2. Average score across all quiz attempts
        $stmtScore = $pdo->prepare('
            SELECT AVG(score) as avg_score
            FROM lms_quiz_attempts
            WHERE project_id = :project_id AND course_id = :course_id
        ');
        $stmtScore->execute([':project_id' => $projectId, ':course_id' => $courseId]);
        $scoreRow = $stmtScore->fetch();
        $avgScore = $scoreRow && $scoreRow['avg_score'] !== null ? round((float)$scoreRow['avg_score']) : 0;

        // 3. Certified count
        $stmtCerts = $pdo->prepare('
            SELECT COUNT(*) FROM lms_certificates WHERE project_id = :project_id AND course_id = :course_id
        ');
        $stmtCerts->execute([':project_id' => $projectId, ':course_id' => $courseId]);
        $certifiedCount = (int)$stmtCerts->fetchColumn();

        // 4. Roster query (ordered by last active descending)
        $stmtRoster = $pdo->prepare('
            SELECT 
                username, percentage, state, last_accessed_at, certificate_id
            FROM lms_progress
            WHERE project_id = :project_id AND course_id = :course_id
            ORDER BY last_accessed_at DESC
        ');
        $stmtRoster->execute([':project_id' => $projectId, ':course_id' => $courseId]);
        $rosterRows = $stmtRoster->fetchAll() ?: [];

        $formattedRoster = [];
        foreach ($rosterRows as $row) {
            $formattedRoster[] = [
                'username' => $row['username'],
                'percentage' => (int)$row['percentage'],
                'state' => $row['state'],
                'last_accessed' => date('Y-m-d H:i', (int)$row['last_accessed_at']),
                'certificate_id' => $row['certificate_id'],
            ];
        }

        return [
            'total_students' => (int)$counts['total_students'],
            'completed_students' => (int)$counts['completed_students'],
            'in_progress_students' => (int)$counts['in_progress_students'],
            'total_enrolled' => (int)$counts['total_students'],
            'completed_count' => (int)$counts['completed_students'],
            'certified_count' => $certifiedCount,
            'average_quiz_score' => (int)$avgScore,
            'roster' => $formattedRoster,
        ];
    }

    /**
     * Stream or fetch gradebook rows directly from indexed ledger.
     */
    public static function getGradebookRows(string $projectId, ?string $courseId = null, ?array $project = null): array
    {
        $pdo = self::getDatabase($projectId, $project);
        if (!$pdo) {
            return [];
        }

        $sql = '
            SELECT 
                p.username,
                p.course_id,
                p.state,
                p.percentage,
                p.percentage as progress_percent,
                p.enrolled_at,
                p.last_accessed_at,
                p.certificate_id,
                p.certificate_id as certificate_code,
                (SELECT AVG(score) FROM lms_quiz_attempts qa 
                 WHERE qa.project_id = p.project_id AND qa.course_id = p.course_id AND qa.username = p.username) as avg_score
            FROM lms_progress p
            WHERE p.project_id = :project_id
        ';
        $params = [':project_id' => $projectId];

        if ($courseId) {
            $sql .= ' AND p.course_id = :course_id';
            $params[':course_id'] = $courseId;
        }

        $sql .= ' ORDER BY p.course_id ASC, p.percentage DESC, p.last_accessed_at DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Self-healing rebuild: Scan JSON progress and certificate files on disk
     * and synchronize them into the SQLite database.
     */
    public static function syncFromFilesystem(string $projectId, array $project): int
    {
        $coursesDir = LmsService::getCoursesDir($project);
        $progressDir = $coursesDir . '/.progress';
        $certDir = $coursesDir . '/.certificates';
        $synced = 0;

        $pdo = self::getDatabase($projectId, $project);
        if (!$pdo) {
            return 0;
        }

        $pdo->beginTransaction();
        try {
            // 1. Sync Progress files
            if (is_dir($progressDir)) {
                $files = glob($progressDir . '/*.json');
                foreach ($files as $file) {
                    $raw = @file_get_contents($file);
                    if (!$raw) continue;
                    $data = json_decode($raw, true);
                    if (!$data || empty($data['username']) || empty($data['course_id'])) continue;

                    self::recordProgress($projectId, $data, $project);

                    // Sync any embedded quiz attempts
                    foreach ($data['quiz_attempts'] ?? [] as $lessonId => $qa) {
                        if (isset($qa['score'])) {
                            self::recordAttempt(
                                $projectId,
                                $data['course_id'],
                                (string)$lessonId,
                                $data['username'],
                                (int)$qa['score'],
                                !empty($qa['passed']),
                                (int)($qa['attempts_count'] ?? 1),
                                $project
                            );
                        }
                    }
                    $synced++;
                }
            }

            // 2. Sync Certificates
            if (is_dir($certDir)) {
                $certFiles = glob($certDir . '/*.json');
                foreach ($certFiles as $cFile) {
                    $rawCert = @file_get_contents($cFile);
                    if (!$rawCert) continue;
                    $cData = json_decode($rawCert, true);
                    if (!$cData || empty($cData['certificate_id'])) continue;

                    self::recordCertificate($projectId, $cData, $project);
                }
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Failed to sync LMS ledger: ' . $e->getMessage());
        }

        return $synced;
    }
}
