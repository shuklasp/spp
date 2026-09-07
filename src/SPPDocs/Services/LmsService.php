<?php

namespace App\SPPDocs\Services;

use Symfony\Component\Yaml\Yaml;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\MarkdownConverter;

/**
 * LmsService
 * Enterprise-grade Learning Management System engine for SPPDocs.
 * Handles Git-native course authoring, curriculum parsing, assessment grading,
 * progress state tracking, and cryptographic certificate minting.
 */
class LmsService
{
    private static ?MarkdownConverter $converter = null;

    private static function getMarkdownConverter(): MarkdownConverter
    {
        if (self::$converter === null) {
            $environment = new Environment([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new TableExtension());
            $environment->addExtension(new HeadingPermalinkExtension());
            $environment->addExtension(new FrontMatterExtension());
            self::$converter = new MarkdownConverter($environment);
        }
        return self::$converter;
    }

    /**
     * Resolves the courses directory on the filesystem for a project.
     */
    public static function getCoursesDir(array $project): string
    {
        $dir = $project['courses_dir'] ?? (dirname($project['_config_path'] ?? '') . '/courses');
        if (strpos($dir, 'C:/') === 0 || strpos($dir, 'c:/') === 0) {
            $dir = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $dir);
        }
        if (!str_starts_with($dir, '/') && !str_contains($dir, ':\\')) {
            $dir = dirname(SPP_BASE_DIR) . '/' . $dir;
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    /**
     * Atomically writes string content to a file using temp file creation,
     * exclusive lock, buffer flush, and atomic rename.
     */
    public static function atomicWriteFile(string $filePath, string $content): bool
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $tempFile = @tempnam($dir, 'spp_tmp_');
        if (!$tempFile) {
            $tempFile = $dir . '/tmp_' . bin2hex(random_bytes(6)) . '.tmp';
        }

        $handle = @fopen($tempFile, 'c+');
        if (!$handle) {
            return (bool)@file_put_contents($filePath, $content, LOCK_EX);
        }

        flock($handle, LOCK_EX);
        ftruncate($handle, 0);
        fwrite($handle, $content);
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        if (DIRECTORY_SEPARATOR === '\\' && file_exists($filePath)) {
            @unlink($filePath);
        }

        $renamed = @rename($tempFile, $filePath);
        if (!$renamed) {
            @copy($tempFile, $filePath);
            @unlink($tempFile);
        }

        return file_exists($filePath);
    }

    /**
     * Atomically writes JSON to a file with LOCK_EX and atomic rename.
     */
    public static function atomicWriteJson(string $filePath, array $data): bool
    {
        return self::atomicWriteFile($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Safely reads JSON from a file using shared read lock.
     */
    public static function safeReadJson(string $filePath): ?array
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $handle = @fopen($filePath, 'r');
        if (!$handle) {
            $raw = @file_get_contents($filePath);
            return $raw ? json_decode($raw, true) : null;
        }

        flock($handle, LOCK_SH);
        $content = stream_get_contents($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        return $content ? json_decode($content, true) : null;
    }

    /**
     * Retrieve all courses for a project.
     */
    public static function getAllCourses(string $projectId, array $project, bool $includeUnpublished = false): array
    {
        $coursesDir = self::getCoursesDir($project);
        $courses = [];

        if (!is_dir($coursesDir)) {
            return [];
        }

        $items = glob($coursesDir . '/*', GLOB_ONLYDIR);
        if (!$items) {
            return [];
        }

        foreach ($items as $item) {
            $courseId = basename($item);
            if (str_starts_with($courseId, '.')) {
                continue; // Skip hidden dirs like .progress, .certificates
            }

            $course = self::getCourse($projectId, $project, $courseId);
            if ($course) {
                if (!$includeUnpublished && empty($course['published'])) {
                    continue;
                }
                $courses[] = $course;
            }
        }

        usort($courses, function ($a, $b) {
            return ($a['order'] ?? 100) <=> ($b['order'] ?? 100);
        });

        return $courses;
    }

    /**
     * Retrieve a specific course by ID.
     */
    public static function getCourse(string $projectId, array $project, string $courseId): ?array
    {
        $safeId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $courseId);
        $coursesDir = self::getCoursesDir($project);
        $courseDir = $coursesDir . '/' . $safeId;
        $courseYaml = $courseDir . '/course.yml';

        if (!file_exists($courseYaml)) {
            return null;
        }

        $data = Yaml::parseFile($courseYaml) ?: [];
        $data['id'] = $safeId;
        $data['title'] = $data['title'] ?? ucwords(str_replace(['-', '_'], ' ', $safeId));
        $data['description'] = $data['description'] ?? '';
        $data['tagline'] = $data['tagline'] ?? '';
        $data['level'] = $data['level'] ?? 'Intermediate';
        $data['category'] = $data['category'] ?? 'General';
        $data['duration'] = $data['duration'] ?? '2 hours';
        $data['badge_icon'] = $data['badge_icon'] ?? '🎓';
        $data['published'] = !empty($data['published']);
        $data['passing_score'] = (int)($data['passing_score'] ?? 80);
        $data['progression_mode'] = in_array($data['progression_mode'] ?? '', ['linear', 'free']) ? $data['progression_mode'] : 'free';
        $data['certificate_enabled'] = isset($data['certificate_enabled']) ? !empty($data['certificate_enabled']) : true;
        $data['modules'] = $data['modules'] ?? [];

        // Compute total lessons and quizzes count
        $totalLessons = 0;
        $totalQuizzes = 0;
        foreach ($data['modules'] as $mod) {
            foreach ($mod['lessons'] ?? [] as $lsn) {
                if (($lsn['type'] ?? 'reading') === 'quiz') {
                    $totalQuizzes++;
                } else {
                    $totalLessons++;
                }
            }
        }
        $data['total_lessons'] = $totalLessons;
        $data['total_quizzes'] = $totalQuizzes;

        return $data;
    }

    /**
     * Save course metadata to course.yml.
     */
    public static function saveCourse(string $projectId, array $project, array $courseData): bool
    {
        $safeId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $courseData['id'] ?? '');
        if (!$safeId) {
            return false;
        }

        $coursesDir = self::getCoursesDir($project);
        $courseDir = $coursesDir . '/' . $safeId;
        if (!is_dir($courseDir)) {
            @mkdir($courseDir, 0777, true);
        }

        $courseYaml = $courseDir . '/course.yml';
        $existing = file_exists($courseYaml) ? (Yaml::parseFile($courseYaml) ?: []) : [];

        $merged = array_merge($existing, $courseData);
        $merged['id'] = $safeId;
        $merged['progression_mode'] = in_array($courseData['progression_mode'] ?? ($existing['progression_mode'] ?? 'free'), ['linear', 'free']) ? ($courseData['progression_mode'] ?? ($existing['progression_mode'] ?? 'free')) : 'free';
        unset($merged['total_lessons'], $merged['total_quizzes'], $merged['user_progress'], $merged['stats']);

        return self::atomicWriteFile($courseYaml, Yaml::dump($merged, 6, 2));
    }

    /**
     * Delete a course and its files.
     */
    public static function deleteCourse(string $projectId, array $project, string $courseId): bool
    {
        $safeId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $courseId);
        $coursesDir = self::getCoursesDir($project);
        $courseDir = $coursesDir . '/' . $safeId;

        if (is_dir($courseDir)) {
            self::recursiveRemoveDir($courseDir);
            return true;
        }
        return false;
    }

    /**
     * Retrieve a specific lesson within a course.
     */
    public static function getLesson(string $projectId, array $project, string $courseId, string $lessonId): ?array
    {
        $course = self::getCourse($projectId, $project, $courseId);
        if (!$course) {
            return null;
        }

        $coursesDir = self::getCoursesDir($project);
        $courseDir = $coursesDir . '/' . $course['id'];

        $targetLesson = null;
        $targetModule = null;
        $prevLesson = null;
        $nextLesson = null;
        $foundCurrent = false;

        // Flatten all lessons to establish prev/next navigation links
        $allFlat = [];
        foreach ($course['modules'] as $mIndex => $mod) {
            foreach ($mod['lessons'] ?? [] as $lIndex => $lsn) {
                $lsn['module_id'] = $mod['id'];
                $lsn['module_title'] = $mod['title'];
                $allFlat[] = $lsn;
            }
        }

        foreach ($allFlat as $idx => $lsn) {
            if ($lsn['id'] === $lessonId) {
                $targetLesson = $lsn;
                $prevLesson = $allFlat[$idx - 1] ?? null;
                $nextLesson = $allFlat[$idx + 1] ?? null;
                break;
            }
        }

        if (!$targetLesson) {
            return null;
        }

        $targetLesson['course_id'] = $course['id'];
        $targetLesson['course_title'] = $course['title'];
        $targetLesson['prev_lesson'] = $prevLesson;
        $targetLesson['next_lesson'] = $nextLesson;

        // If it's a quiz lesson
        if (($targetLesson['type'] ?? '') === 'quiz') {
            $quizFile = $targetLesson['quiz_file'] ?? ($targetLesson['id'] . '.yml');
            $fullQuizPath = $courseDir . '/' . $quizFile;
            if (file_exists($fullQuizPath)) {
                $targetLesson['quiz_data'] = self::prepareQuizForDisplay(Yaml::parseFile($fullQuizPath) ?: []);
            }
            $targetLesson['content_html'] = '';
            return $targetLesson;
        }

        // Standard Markdown reading lesson
        $filePath = $targetLesson['file'] ?? ($targetLesson['id'] . '.md');
        $fullPath = $courseDir . '/' . $filePath;

        $rawContent = file_exists($fullPath) ? file_get_contents($fullPath) : "# " . ($targetLesson['title'] ?? 'Lesson') . "\n\nContent coming soon.";

        $converter = self::getMarkdownConverter();
        $converted = $converter->convert($rawContent);

        $frontmatter = [];
        if ($converted instanceof \League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter) {
            $frontmatter = $converted->getFrontMatter() ?: [];
        }

        $targetLesson['frontmatter'] = $frontmatter;
        $targetLesson['content_html'] = $converted->getContent();

        // Check for attached quiz within markdown frontmatter or file
        if (!empty($targetLesson['quiz_file'])) {
            $qPath = $courseDir . '/' . $targetLesson['quiz_file'];
            if (file_exists($qPath)) {
                $targetLesson['quiz_data'] = self::prepareQuizForDisplay(Yaml::parseFile($qPath) ?: []);
            }
        } elseif (!empty($frontmatter['quiz'])) {
            $targetLesson['quiz_data'] = self::prepareQuizForDisplay($frontmatter['quiz']);
        }

        return $targetLesson;
    }

    /**
     * Prepares quiz questions for student display, applying randomization if configured.
     */
    public static function prepareQuizForDisplay(array $quizData): array
    {
        if (empty($quizData['questions']) || !is_array($quizData['questions'])) {
            return $quizData;
        }

        if (!empty($quizData['shuffle_questions'])) {
            shuffle($quizData['questions']);
        }

        if (!empty($quizData['shuffle_options'])) {
            foreach ($quizData['questions'] as &$q) {
                if (!empty($q['options']) && is_array($q['options'])) {
                    shuffle($q['options']);
                }
            }
            unset($q);
        }

        return $quizData;
    }

    /**
     * Grades an assessment submission.
     */
    public static function gradeQuiz(array $quiz, array $submittedAnswers): array
    {
        $questions = $quiz['questions'] ?? [];
        $totalQuestions = count($questions);
        if ($totalQuestions === 0) {
            return [
                'score' => 100,
                'passing_score' => (int)($quiz['passing_score'] ?? 80),
                'passed' => true,
                'correct_count' => 0,
                'total_questions' => 0,
                'feedback' => [],
            ];
        }

        $totalEarnedPoints = 0.0;
        $correctCount = 0;
        $feedback = [];

        foreach ($questions as $q) {
            $qid = $q['id'];
            $type = $q['type'] ?? 'multiple_choice';
            $userAns = $submittedAnswers[$qid] ?? null;
            $isCorrect = false;
            $questionScore = 0.0;

            if ($type === 'multiple_choice') {
                // Find correct option id
                $correctOpt = null;
                foreach ($q['options'] ?? [] as $opt) {
                    if (!empty($opt['correct'])) {
                        $correctOpt = $opt['id'];
                        break;
                    }
                }
                $isCorrect = ($userAns !== null && (string)$userAns === (string)$correctOpt);
                if ($isCorrect) {
                    $questionScore = 1.0;
                    $totalEarnedPoints += 1.0;
                    $correctCount++;
                }
            } elseif ($type === 'multi_select') {
                // Find all correct and incorrect option ids
                $correctOpts = [];
                $distractorOpts = [];
                foreach ($q['options'] ?? [] as $opt) {
                    if (!empty($opt['correct'])) {
                        $correctOpts[] = (string)$opt['id'];
                    } else {
                        $distractorOpts[] = (string)$opt['id'];
                    }
                }

                $userAnsArr = is_array($userAns) ? array_map('strval', $userAns) : [];
                $numCorrect = count($correctOpts);
                $numDistractors = count($distractorOpts);

                if ($numCorrect > 0) {
                    $questionFraction = 0.0;
                    foreach ($userAnsArr as $pickedId) {
                        if (in_array($pickedId, $correctOpts, true)) {
                            $questionFraction += (1.0 / $numCorrect);
                        } elseif (in_array($pickedId, $distractorOpts, true)) {
                            // Negative distractor penalty to deter selecting all choices
                            $penalty = $numDistractors > 0 ? (1.0 / $numDistractors) : 1.0;
                            $questionFraction -= $penalty;
                        }
                    }
                    $questionScore = max(0.0, min(1.0, $questionFraction));
                    $isCorrect = ($questionScore >= 0.999);
                    $totalEarnedPoints += $questionScore;
                    if ($isCorrect) {
                        $correctCount++;
                    }
                } else {
                    $isCorrect = empty($userAnsArr);
                    if ($isCorrect) {
                        $questionScore = 1.0;
                        $totalEarnedPoints += 1.0;
                        $correctCount++;
                    }
                }
            } elseif ($type === 'code_check') {
                $expected = trim($q['expected'] ?? '');
                $actual = trim((string)$userAns);
                $isCorrect = (strcasecmp($expected, $actual) === 0);
                if ($isCorrect) {
                    $questionScore = 1.0;
                    $totalEarnedPoints += 1.0;
                    $correctCount++;
                }
            }

            $feedback[$qid] = [
                'is_correct' => $isCorrect,
                'score' => round($questionScore, 2),
                'user_answer' => $userAns,
                'explanation' => $q['explanation'] ?? '',
            ];
        }

        $scorePercent = round(($totalEarnedPoints / $totalQuestions) * 100);
        $passingScore = (int)($quiz['passing_score'] ?? 80);
        $passed = ($scorePercent >= $passingScore);

        return [
            'score' => (int)$scorePercent,
            'score_percent' => (int)$scorePercent,
            'passing_score' => $passingScore,
            'passed' => $passed,
            'correct_count' => $correctCount,
            'total_questions' => $totalQuestions,
            'feedback' => $feedback,
        ];
    }

    /**
     * Retrieve student progress for a course.
     */
    public static function getUserProgress(string $projectId, array $project, string $courseId, string $username): array
    {
        $safeUser = preg_replace('/[^a-zA-Z0-9_\-]/', '', $username);
        $safeCourse = preg_replace('/[^a-zA-Z0-9_\-]/', '', $courseId);
        $coursesDir = self::getCoursesDir($project);
        $progressDir = $coursesDir . '/.progress';
        if (!is_dir($progressDir)) {
            @mkdir($progressDir, 0777, true);
        }

        $progressFile = $progressDir . '/' . $safeUser . '_' . $safeCourse . '.json';
        $data = self::safeReadJson($progressFile) ?: [
            'username' => $username,
            'course_id' => $safeCourse,
            'state' => 'enrolled',
            'enrolled_at' => time(),
            'last_accessed_at' => time(),
            'completed_lessons' => [],
            'quiz_attempts' => [],
            'certificate_id' => null,
        ];

        // Calculate progress percentage
        $course = self::getCourse($projectId, $project, $safeCourse);
        $totalItems = 0;
        if ($course) {
            foreach ($course['modules'] as $mod) {
                $totalItems += count($mod['lessons'] ?? []);
            }
        }

        $completedCount = count($data['completed_lessons'] ?? []);
        $data['completed_count'] = $completedCount;
        $data['total_items'] = $totalItems;
        $data['percentage'] = $totalItems > 0 ? min(100, round(($completedCount / $totalItems) * 100)) : 0;
        $data['is_completed'] = ($data['percentage'] >= 100);

        return $data;
    }

    /**
     * Save student progress using atomic file writes and write-through SQLite ledger.
     */
    public static function saveUserProgress(string $projectId, array $project, array $progress): bool
    {
        $safeUser = preg_replace('/[^a-zA-Z0-9_\-]/', '', $progress['username'] ?? '');
        $safeCourse = preg_replace('/[^a-zA-Z0-9_\-]/', '', $progress['course_id'] ?? '');
        if (!$safeUser || !$safeCourse) {
            return false;
        }

        $coursesDir = self::getCoursesDir($project);
        $progressDir = $coursesDir . '/.progress';
        if (!is_dir($progressDir)) {
            @mkdir($progressDir, 0777, true);
        }

        $progress['last_accessed_at'] = time();
        $progressFile = $progressDir . '/' . $safeUser . '_' . $safeCourse . '.json';

        // 1. Atomic write to JSON (Git-porcelain preservation)
        $saved = self::atomicWriteJson($progressFile, $progress);

        // 2. Write-through to high-performance SQLite caching ledger
        if (class_exists(\App\SPPDocs\Services\LmsLedgerService::class)) {
            \App\SPPDocs\Services\LmsLedgerService::recordProgress($projectId, $progress, $project);
        }

        return $saved;
    }

    /**
     * Mark a lesson as completed.
     */
    public static function markLessonComplete(string $projectId, array $project, string $courseId, string $lessonId, string $username): array
    {
        $prog = self::getUserProgress($projectId, $project, $courseId, $username);
        $completed = $prog['completed_lessons'] ?? [];

        if (!in_array($lessonId, $completed)) {
            $completed[] = $lessonId;
            $prog['completed_lessons'] = $completed;
        }

        if ($prog['state'] === 'enrolled') {
            $prog['state'] = 'in_progress';
        }

        self::saveUserProgress($projectId, $project, $prog);

        // Fire Dual Event Bus
        if (class_exists(\SPP\SPPEvent::class)) {
            $payload = [
                'project_id' => $projectId,
                'course_id' => $courseId,
                'lesson_id' => $lessonId,
                'username' => $username,
                'percentage' => $prog['percentage'] ?? 0,
            ];
            $eventParams = class_exists(\SPP\EventParams::class) ? new \SPP\EventParams($payload) : $payload;
            \SPP\SPPEvent::fireEvent('lms.lesson_completed', $eventParams);
            \SPP\SPPEvent::triggerHook('lms:lesson_completed', $payload);
        }

        return self::getUserProgress($projectId, $project, $courseId, $username);
    }

    /**
     * Mark a lesson as incomplete.
     */
    public static function markLessonIncomplete(string $projectId, array $project, string $courseId, string $lessonId, string $username): array
    {
        $prog = self::getUserProgress($projectId, $project, $courseId, $username);
        $completed = $prog['completed_lessons'] ?? [];

        $prog['completed_lessons'] = array_values(array_filter($completed, fn($id) => $id !== $lessonId));
        self::saveUserProgress($projectId, $project, $prog);

        return self::getUserProgress($projectId, $project, $courseId, $username);
    }

    /**
     * Record a quiz attempt and mark lesson completed if passed.
     */
    public static function recordQuizAttempt(string $projectId, array $project, string $courseId, string $quizId, string $username, array $gradeResult): array
    {
        $prog = self::getUserProgress($projectId, $project, $courseId, $username);
        $prevAttempt = $prog['quiz_attempts'][$quizId] ?? [];
        $attemptCount = ($prevAttempt['attempts_count'] ?? 0) + 1;

        $prog['quiz_attempts'][$quizId] = [
            'score' => $gradeResult['score'],
            'passed' => $gradeResult['passed'],
            'attempted_at' => time(),
            'attempts_count' => $attemptCount,
        ];

        if ($gradeResult['passed']) {
            $completed = $prog['completed_lessons'] ?? [];
            if (!in_array($quizId, $completed)) {
                $completed[] = $quizId;
                $prog['completed_lessons'] = $completed;
            }
        }

        self::saveUserProgress($projectId, $project, $prog);

        // Write-through to high-performance SQLite caching ledger
        if (class_exists(\App\SPPDocs\Services\LmsLedgerService::class)) {
            \App\SPPDocs\Services\LmsLedgerService::recordAttempt(
                $projectId,
                $courseId,
                $quizId,
                $username,
                (int)$gradeResult['score'],
                !empty($gradeResult['passed']),
                $attemptCount,
                $project
            );
        }

        // Fire Dual Event Bus
        if (class_exists(\SPP\SPPEvent::class)) {
            $payload = [
                'project_id' => $projectId,
                'course_id' => $courseId,
                'quiz_id' => $quizId,
                'username' => $username,
                'score' => $gradeResult['score'],
                'passed' => $gradeResult['passed'],
                'attempts_count' => $attemptCount,
            ];
            $eventParams = class_exists(\SPP\EventParams::class) ? new \SPP\EventParams($payload) : $payload;
            \SPP\SPPEvent::fireEvent('lms.quiz_submitted', $eventParams);
            \SPP\SPPEvent::triggerHook('lms:quiz_submitted', $payload);
        }

        return self::getUserProgress($projectId, $project, $courseId, $username);
    }

    /**
     * Check course completion and mint a verifiable certificate if qualified.
     */
    public static function checkAndIssueCertificate(string $projectId, array $project, string $courseId, string $username, string $displayName): ?array
    {
        $course = self::getCourse($projectId, $project, $courseId);
        if (!$course || empty($course['certificate_enabled'])) {
            return null;
        }

        $prog = self::getUserProgress($projectId, $project, $courseId, $username);
        if ($prog['percentage'] < 100) {
            return null; // Not all lessons completed
        }

        // If certificate was already issued, return it
        if (!empty($prog['certificate_id'])) {
            $cert = self::getCertificate($projectId, $project, $prog['certificate_id']);
            if ($cert) {
                return $cert;
            }
        }

        // Mint new Certificate
        $issueTime = time();
        $randomHash = strtoupper(bin2hex(random_bytes(4)));
        $certCode = 'SPP-LMS-' . date('Y', $issueTime) . '-' . $randomHash;

        // Cryptographic verification hash
        $secret = 'sppdocs_secret_cert_' . $projectId;
        $hashSignature = hash_hmac('sha256', $certCode . '|' . $username . '|' . $courseId . '|' . $issueTime, $secret);

        $certData = [
            'certificate_id' => $certCode,
            'project_id' => $projectId,
            'course_id' => $courseId,
            'course_title' => $course['title'],
            'student_name' => $displayName ?: $username,
            'username' => $username,
            'issued_at' => $issueTime,
            'issue_date' => date('F j, Y', $issueTime),
            'instructor' => $course['instructor']['name'] ?? 'SPPDocs Academy',
            'instructor_title' => $course['instructor']['title'] ?? 'Authorized Instructor',
            'verification_hash' => $hashSignature,
            'verification_url' => \SPP\App::url('lms/verify') . '?code=' . $certCode,
        ];

        // Save Certificate file atomically
        $coursesDir = self::getCoursesDir($project);
        $certDir = $coursesDir . '/.certificates';
        if (!is_dir($certDir)) {
            @mkdir($certDir, 0777, true);
        }
        self::atomicWriteJson($certDir . '/' . $certCode . '.json', $certData);

        // Write-through to SQLite ledger
        if (class_exists(\App\SPPDocs\Services\LmsLedgerService::class)) {
            \App\SPPDocs\Services\LmsLedgerService::recordCertificate($projectId, $certData, $project);
        }

        // Update student progress state to certified
        $prog['state'] = 'certified';
        $prog['certificate_id'] = $certCode;
        self::saveUserProgress($projectId, $project, $prog);

        // Fire Dual Event Bus
        if (class_exists(\SPP\SPPEvent::class)) {
            $payload = [
                'project_id' => $projectId,
                'course_id' => $courseId,
                'username' => $username,
                'student_name' => $certData['student_name'],
                'certificate_id' => $certCode,
                'issue_date' => $certData['issue_date'],
            ];
            $eventParams = class_exists(\SPP\EventParams::class) ? new \SPP\EventParams($payload) : $payload;
            \SPP\SPPEvent::fireEvent('lms.certified', $eventParams);
            \SPP\SPPEvent::triggerHook('lms:certified', $payload);
        }

        return $certData;
    }

    /**
     * Retrieve certificate by certificate ID.
     */
    public static function getCertificate(string $projectId, array $project, string $certId): ?array
    {
        $safeCert = preg_replace('/[^a-zA-Z0-9_\-]/', '', $certId);
        $coursesDir = self::getCoursesDir($project);
        $certFile = $coursesDir . '/.certificates/' . $safeCert . '.json';

        if (file_exists($certFile)) {
            return self::safeReadJson($certFile);
        }
        return null;
    }

    /**
     * Search all projects or global certificate registry to verify a certificate code.
     */
    public static function findCertificateByCode(string $certCode): ?array
    {
        $safeCode = preg_replace('/[^a-zA-Z0-9_\-]/', '', $certCode);
        if (!$safeCode) {
            return null;
        }

        // Search in all project course directories
        $configFile = __DIR__ . '/../etc/sppdocs.yml';
        if (file_exists($configFile)) {
            $mainConfig = Yaml::parseFile($configFile);
            foreach ($mainConfig['projects'] ?? [] as $pId => $pPath) {
                if (strpos($pPath, 'C:/') === 0 || strpos($pPath, 'c:/') === 0) {
                    $pPath = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $pPath);
                }
                if (!str_starts_with($pPath, '/') && !str_contains($pPath, ':\\')) {
                    $pPath = dirname(SPP_BASE_DIR) . '/' . $pPath;
                }
                $certFile = dirname($pPath) . '/courses/.certificates/' . $safeCode . '.json';
                if (file_exists($certFile)) {
                    return json_decode(file_get_contents($certFile), true);
                }
            }
        }
        return null;
    }

    /**
     * Aggregate learning data for a student's personal dashboard.
     */
    public static function getUserDashboard(string $projectId, array $project, string $username): array
    {
        $allCourses = self::getAllCourses($projectId, $project, false);
        $inProgress = [];
        $completed = [];
        $certificates = [];
        $totalQuizzesPassed = 0;

        foreach ($allCourses as $c) {
            $prog = self::getUserProgress($projectId, $project, $c['id'], $username);
            $c['user_progress'] = $prog;

            if (!empty($prog['quiz_attempts'])) {
                foreach ($prog['quiz_attempts'] as $qa) {
                    if (!empty($qa['passed'])) {
                        $totalQuizzesPassed++;
                    }
                }
            }

            if ($prog['percentage'] >= 100) {
                $completed[] = $c;
                if (!empty($prog['certificate_id'])) {
                    $cert = self::getCertificate($projectId, $project, $prog['certificate_id']);
                    if ($cert) {
                        $certificates[] = $cert;
                    }
                }
            } elseif ($prog['percentage'] > 0 || ($prog['state'] ?? '') === 'in_progress') {
                $inProgress[] = $c;
            }
        }

        return [
            'username' => $username,
            'in_progress_courses' => $inProgress,
            'completed_courses' => $completed,
            'certificates' => $certificates,
            'total_enrolled' => count($inProgress) + count($completed),
            'total_completed' => count($completed),
            'total_quizzes_passed' => $totalQuizzesPassed,
        ];
    }

    /**
     * Aggregate teacher and author analytics across all learners for a course.
     */
    public static function getCourseAnalytics(string $projectId, array $project, string $courseId): array
    {
        $course = self::getCourse($projectId, $project, $courseId);
        if (!$course) {
            return [];
        }

        // Fast path: O(1) query from high-performance SQLite caching ledger
        if (class_exists(\App\SPPDocs\Services\LmsLedgerService::class)) {
            $ledger = \App\SPPDocs\Services\LmsLedgerService::getAggregatedAnalytics($projectId, $courseId, $project);
            if ($ledger !== null && $ledger['total_students'] > 0) {
                $ledger['course'] = $course;
                return $ledger;
            }
        }

        $coursesDir = self::getCoursesDir($project);
        $progressDir = $coursesDir . '/.progress';
        $roster = [];
        $completedCount = 0;
        $inProgressCount = 0;
        $totalScore = 0;
        $scoreCount = 0;

        if (is_dir($progressDir)) {
            $files = glob($progressDir . '/*_' . $course['id'] . '.json');
            foreach ($files as $f) {
                $data = json_decode(file_get_contents($f), true);
                if (!$data) continue;

                $prog = self::getUserProgress($projectId, $project, $course['id'], $data['username']);
                if ($prog['percentage'] >= 100) {
                    $completedCount++;
                } else {
                    $inProgressCount++;
                }

                foreach ($data['quiz_attempts'] ?? [] as $qa) {
                    if (isset($qa['score'])) {
                        $totalScore += (int)$qa['score'];
                        $scoreCount++;
                    }
                }

                $roster[] = [
                    'username' => $data['username'],
                    'percentage' => $prog['percentage'],
                    'state' => $data['state'] ?? 'in_progress',
                    'last_accessed' => date('Y-m-d H:i', $data['last_accessed_at'] ?? time()),
                    'certificate_id' => $data['certificate_id'] ?? null,
                ];
            }
        }

        return [
            'course' => $course,
            'total_students' => count($roster),
            'completed_students' => $completedCount,
            'in_progress_students' => $inProgressCount,
            'average_quiz_score' => $scoreCount > 0 ? round($totalScore / $scoreCount) : 0,
            'roster' => $roster,
        ];
    }

    /**
     * One-click scafolder to generate an end-to-end sample course with real curriculum.
     */
    public static function scaffoldSampleCourse(string $projectId, array $project): string
    {
        $courseId = 'intro-to-spp';
        $coursesDir = self::getCoursesDir($project);
        $courseDir = $coursesDir . '/' . $courseId;

        if (!is_dir($courseDir)) {
            @mkdir($courseDir, 0777, true);
        }

        $mod1Dir = $courseDir . '/mod_foundations';
        $mod2Dir = $courseDir . '/mod_workflows';
        @mkdir($mod1Dir, 0777, true);
        @mkdir($mod2Dir, 0777, true);

        // Course YAML
        $courseYaml = [
            'id' => $courseId,
            'title' => 'Mastering SPP Framework',
            'tagline' => 'From Novice to Enterprise Architect',
            'description' => 'A comprehensive, hands-on masterclass covering the request lifecycle, CLI SAPI security, high-performance ViewControllers, and Saga workflow orchestration.',
            'level' => 'Intermediate',
            'category' => 'Web Architecture',
            'duration' => '2 hours',
            'badge_icon' => '🎓',
            'published' => true,
            'passing_score' => 80,
            'certificate_enabled' => true,
            'instructor' => [
                'name' => 'Prof. Satya Prakash Shukla',
                'title' => 'Chief Framework Architect',
                'bio' => 'Enterprise software architect specializing in high-performance web kernels and resilient state machines.',
            ],
            'prerequisites' => [
                'Basic familiarity with PHP 8.2+',
                'Understanding of MVC paradigms and HTTP basics',
            ],
            'modules' => [
                [
                    'id' => 'mod_foundations',
                    'title' => 'Module 1: Architecture & SAPI Core',
                    'description' => 'Explore the SPP request lifecycle, zero-cost routing, and strict CLI SAPI guarding.',
                    'lessons' => [
                        [
                            'id' => '01_lifecycle',
                            'title' => 'The SPP Request Lifecycle',
                            'duration' => '15 mins',
                            'type' => 'reading',
                            'file' => 'mod_foundations/01_lifecycle.md',
                        ],
                        [
                            'id' => '02_sapi_guarding',
                            'title' => 'CLI SAPI Security & Daemon Architecture',
                            'duration' => '20 mins',
                            'type' => 'reading',
                            'file' => 'mod_foundations/02_sapi_guarding.md',
                        ],
                        [
                            'id' => 'quiz_foundations',
                            'title' => 'Foundations Knowledge Check',
                            'duration' => '10 mins',
                            'type' => 'quiz',
                            'passing_score' => 80,
                            'quiz_file' => 'mod_foundations/quiz_foundations.yml',
                        ],
                    ],
                ],
                [
                    'id' => 'mod_workflows',
                    'title' => 'Module 2: Workflows, CQRS & Saga Orchestration',
                    'description' => 'Master state machines, parallel markings, compensating transactions, and audit trails.',
                    'lessons' => [
                        [
                            'id' => '01_workflows',
                            'title' => 'Designing Resilient State Machines',
                            'duration' => '25 mins',
                            'type' => 'reading',
                            'file' => 'mod_workflows/01_workflows.md',
                        ],
                        [
                            'id' => 'quiz_final',
                            'title' => 'Final Certification Assessment',
                            'duration' => '15 mins',
                            'type' => 'quiz',
                            'passing_score' => 85,
                            'quiz_file' => 'mod_workflows/quiz_final.yml',
                        ],
                    ],
                ],
            ],
        ];

        file_put_contents($courseDir . '/course.yml', Yaml::dump($courseYaml, 6, 2));

        // Lesson 1 Markdown
        $l1 = <<<MD
---
title: The SPP Request Lifecycle
duration: 15 mins
summary: Discover how incoming HTTP requests flow from .htaccess through the router to ViewControllers.
---

# The SPP Request Lifecycle

Welcome to the **Mastering SPP Framework** academy! In this first lesson, we will unpack how SPP delivers sub-millisecond request execution.

## 1. Single Entry Point Architecture

Every incoming request enters through `public/index.php`. The kernel initializes only the necessary bootstrap providers before evaluating routes.

```php
// Request boot sequence
\$app = \\SPP\\App::getInstance();
\$app->boot();
\$app->handleRequest();
```

## 2. Zero-Cost Route Dispatching

Unlike heavyweight frameworks that inspect hundreds of classes on every boot, SPP compiles page routes in `etc/apps/{AppName}/pages.yml`.

### Key Benefits:
- **Zero Inline HTML**: Controllers serve clean external templates (`.blade.php`).
- **O(1) Route Resolution**: Exact-match hash tables for static endpoints.
- **Strict Content Negotiation**: Native support for HTMX partials and Turbo Streams.

> [!TIP]
> Always verify that your controller extends `\\SPPMod\\SPPView\\ViewController` to inherit smart HTMX content negotiation and partial rendering helpers.
MD;
        file_put_contents($mod1Dir . '/01_lifecycle.md', $l1);

        // Lesson 2 Markdown
        $l2 = <<<MD
---
title: CLI SAPI Security & Daemon Architecture
duration: 20 mins
summary: Learn strict CLI SAPI guarding and how to build secure system commands and daemons.
---

# CLI SAPI Security & Daemon Architecture

High-privilege routines such as database migrations, background queue workers, and deployment orchestration must never be reachable via web HTTP requests.

## 1. The `isCLIOnly()` Security Guard

SPP enforces strict CLI SAPI guarding. Any command executing system operations or file modifications must override:

```php
public function isCLIOnly(): bool
{
    return true;
}
```

When `CommandManager::execute()` runs, it checks `PHP_SAPI !== 'cli'`. If accessed over HTTP, execution terminates immediately with a security block.

## 2. DDL Identifier Sanitization

When writing schema migrations or inspection tools, never interpolate raw strings into SQL DDL:

```php
// ✅ Correct: Strict sanitization
\$safeTable = SchemaValidator::isValidIdentifier(\$tableName);
\$sql = "DROP TABLE IF EXISTS " . \$this->escapeIdentifier(\$safeTable);
```

## 3. Distributed Mutex Locking

All deployment commands prevent race conditions using distributed mutex locks:

```php
try {
    \\SPPMod\\SPPDeploy\\Deployer\\TargetConnection::acquireDeploymentLock();
    // Execute critical deployment routine
} finally {
    \\SPPMod\\SPPDeploy\\Deployer\\TargetConnection::releaseDeploymentLock();
}
```
MD;
        file_put_contents($mod1Dir . '/02_sapi_guarding.md', $l2);

        // Quiz 1 YAML
        $q1 = [
            'title' => 'Foundations Knowledge Check',
            'description' => 'Test your understanding of SPP core principles and CLI guarding.',
            'passing_score' => 80,
            'questions' => [
                [
                    'id' => 'q1',
                    'type' => 'multiple_choice',
                    'prompt' => 'Which method must high-privilege CLI commands override to block web SAPI execution?',
                    'options' => [
                        ['id' => 'a', 'text' => 'public function isCLIOnly(): bool { return true; }', 'correct' => true],
                        ['id' => 'b', 'text' => 'public function runCli(): void', 'correct' => false],
                        ['id' => 'c', 'text' => 'protected $cliGuard = 1;', 'correct' => false],
                    ],
                    'explanation' => 'Overriding isCLIOnly(): bool ensures CommandManager blocks execution whenever PHP_SAPI !== "cli".',
                ],
                [
                    'id' => 'q2',
                    'type' => 'multi_select',
                    'prompt' => 'Which template engines are natively supported by the SPP framework?',
                    'options' => [
                        ['id' => 'a', 'text' => 'Blade', 'correct' => true],
                        ['id' => 'b', 'text' => 'Twig', 'correct' => true],
                        ['id' => 'c', 'text' => 'Smarty 1.0', 'correct' => false],
                    ],
                    'explanation' => 'SPP features first-class, high-performance adapters for both Blade and Twig.',
                ],
                [
                    'id' => 'q3',
                    'type' => 'code_check',
                    'prompt' => 'Fill in the blank: What native method on SPPEntity checks if a transition is valid?',
                    'code' => '$entity->____("submit_for_review");',
                    'expected' => 'canTransition',
                    'hint' => 'Native workflow method on SPPEntity',
                    'explanation' => '$entity->canTransition($transitionName) checks whether the current state permits the requested transition.',
                ],
            ],
        ];
        file_put_contents($mod1Dir . '/quiz_foundations.yml', Yaml::dump($q1, 6, 2));

        // Lesson 3 Markdown
        $l3 = <<<MD
---
title: Designing Resilient State Machines
duration: 25 mins
summary: Master workflow states, transitions, Saga compensations, and audit trails.
---

# Designing Resilient State Machines

In enterprise architectures, entities move through complex lifecycles (e.g. `draft` → `pending_review` → `published`).

## 1. Native Entity Workflow APIs

All database models extending `SPPEntity` natively inherit workflow orchestration:

```php
// Check if state transition is allowed
if (\$entity->canTransition('approve')) {
    \$entity->applyTransition('approve', \$currentUser, 'Passed code review');
}
```

## 2. Saga Pattern & Compensating Transactions

When multi-step workflows experience failure, the Saga orchestrator rolls back state using compensating callbacks registered in `WorkflowManager::rollback()`.

## 3. Dual Event Bus Firing

Workflow transitions dispatch events to both the event bus and hook pipelines:
- `\\SPP\\SPPEvent::fireEvent('workflow.after_transition', \$payload)`
- `\\SPP\\SPPEvent::triggerHook('workflow.after_transition', \$payload)`
MD;
        file_put_contents($mod2Dir . '/01_workflows.md', $l3);

        // Quiz 2 YAML
        $q2 = [
            'title' => 'Final Certification Assessment',
            'description' => 'Comprehensive exam to qualify for your Certificate of Completion.',
            'passing_score' => 85,
            'questions' => [
                [
                    'id' => 'q1',
                    'type' => 'multiple_choice',
                    'prompt' => 'How should state transitions be triggered on an SPPEntity?',
                    'options' => [
                        ['id' => 'a', 'text' => 'Using $entity->applyTransition($name, $user, $comment)', 'correct' => true],
                        ['id' => 'b', 'text' => 'Directly mutating $entity->status = "approved"', 'correct' => false],
                        ['id' => 'c', 'text' => 'Writing a raw SQL UPDATE query', 'correct' => false],
                    ],
                    'explanation' => 'applyTransition() enforces guards, triggers lifecycle hooks, and logs state changes to the audit history.',
                ],
                [
                    'id' => 'q2',
                    'type' => 'multiple_choice',
                    'prompt' => 'What pattern does SPP utilize to rollback multi-step distributed operations?',
                    'options' => [
                        ['id' => 'a', 'text' => 'Saga Compensating Transactions', 'correct' => true],
                        ['id' => 'b', 'text' => 'Two-Phase Commit Locks', 'correct' => false],
                        ['id' => 'c', 'text' => 'Process Crash & Restart', 'correct' => false],
                    ],
                    'explanation' => 'SPP workflows support Saga-style compensating transactions for state rollbacks.',
                ],
                [
                    'id' => 'q3',
                    'type' => 'code_check',
                    'prompt' => 'Complete the helper call in a ViewController to transition an entity and negotiate HTMX:',
                    'code' => '$this->____($entity, "approve", $context);',
                    'expected' => 'transitionEntity',
                    'hint' => 'Helper method on ViewController',
                    'explanation' => 'transitionEntity() handles execution and automatically negotiates HTMX or Turbo Stream partials.',
                ],
            ],
        ];
        file_put_contents($mod2Dir . '/quiz_final.yml', Yaml::dump($q2, 6, 2));

        return $courseId;
    }

    /**
     * Determines if a student can access a specific lesson based on progression rules.
     */
    public static function canAccessLesson(string $projectId, array $project, string $courseId, string $lessonId, string $username): bool
    {
        $course = self::getCourse($projectId, $project, $courseId);
        if (!$course) return false;

        // If progression mode is not linear, all lessons are freely accessible
        if (($course['progression_mode'] ?? 'free') !== 'linear') {
            return true;
        }

        // Flatten all lessons in sequential order
        $allFlat = [];
        foreach ($course['modules'] as $mod) {
            foreach ($mod['lessons'] ?? [] as $lsn) {
                $allFlat[] = $lsn;
            }
        }

        if (empty($allFlat)) return false;

        // First lesson is always accessible
        if ($allFlat[0]['id'] === $lessonId) {
            return true;
        }

        // Guest users cannot advance beyond the first lesson in linear courses
        if ($username === 'guest' || !$username) {
            return false;
        }

        $prog = self::getUserProgress($projectId, $project, $courseId, $username);
        $completed = $prog['completed_lessons'] ?? [];

        foreach ($allFlat as $lsn) {
            if ($lsn['id'] === $lessonId) {
                // Reached target lesson and all preceding lessons were cleared!
                return true;
            }
            if (!in_array($lsn['id'], $completed)) {
                return false;
            }
        }

        return false;
    }

    /**
     * Finds the next eligible uncompleted lesson for a student in a linear course.
     */
    public static function getNextEligibleLesson(string $projectId, array $project, string $courseId, string $username): ?array
    {
        $course = self::getCourse($projectId, $project, $courseId);
        if (!$course) return null;

        $prog = ($username && $username !== 'guest') 
            ? self::getUserProgress($projectId, $project, $courseId, $username) 
            : ['completed_lessons' => []];

        $completed = $prog['completed_lessons'] ?? [];

        foreach ($course['modules'] as $mod) {
            foreach ($mod['lessons'] ?? [] as $lsn) {
                if (!in_array($lsn['id'], $completed)) {
                    return $lsn;
                }
            }
        }

        return $course['modules'][0]['lessons'][0] ?? null;
    }

    /**
     * Inspects attempt limits, cooldown timers, and exam readiness for a quiz.
     */
    public static function getQuizStatus(array $quiz, ?array $userAttempt): array
    {
        $maxAttempts = (int)($quiz['max_attempts'] ?? 0);
        $cooldownMinutes = (int)($quiz['cooldown_minutes'] ?? 0);
        $attemptsCount = (int)($userAttempt['attempts_count'] ?? 0);
        $lastAttemptedAt = (int)($userAttempt['attempted_at'] ?? 0);
        $passed = !empty($userAttempt['passed']);

        $canAttempt = true;
        $inCooldown = false;
        $cooldownRemainingSeconds = 0;

        if ($passed) {
            return [
                'can_attempt' => true,
                'is_passed' => true,
                'in_cooldown' => false,
                'cooldown_remaining_seconds' => 0,
                'attempts_count' => $attemptsCount,
                'max_attempts' => $maxAttempts,
                'attempts_left' => ($maxAttempts > 0) ? max(0, $maxAttempts - $attemptsCount) : null,
            ];
        }

        if ($maxAttempts > 0 && $attemptsCount >= $maxAttempts) {
            $canAttempt = false;
        }

        if ($cooldownMinutes > 0 && $lastAttemptedAt > 0) {
            $elapsed = time() - $lastAttemptedAt;
            $cooldownDuration = $cooldownMinutes * 60;
            if ($elapsed < $cooldownDuration) {
                $canAttempt = false;
                $inCooldown = true;
                $cooldownRemainingSeconds = $cooldownDuration - $elapsed;
            }
        }

        return [
            'can_attempt' => $canAttempt,
            'is_passed' => false,
            'in_cooldown' => $inCooldown,
            'cooldown_remaining_seconds' => $cooldownRemainingSeconds,
            'attempts_count' => $attemptsCount,
            'max_attempts' => $maxAttempts,
            'attempts_left' => ($maxAttempts > 0) ? max(0, $maxAttempts - $attemptsCount) : null,
        ];
    }

    /**
     * Retrieve raw markdown and quiz structures for in-browser visual editing.
     */
    public static function getLessonRaw(string $projectId, array $project, string $courseId, string $lessonId): ?array
    {
        $course = self::getCourse($projectId, $project, $courseId);
        if (!$course) return null;

        $coursesDir = self::getCoursesDir($project);
        $courseDir = $coursesDir . '/' . $course['id'];

        $targetLesson = null;
        $targetModule = null;
        foreach ($course['modules'] as $mod) {
            foreach ($mod['lessons'] ?? [] as $lsn) {
                if ($lsn['id'] === $lessonId) {
                    $targetLesson = $lsn;
                    $targetModule = $mod;
                    break 2;
                }
            }
        }

        if (!$targetLesson) {
            return null;
        }

        $targetLesson['module_id'] = $targetModule['id'] ?? '';
        $targetLesson['module_title'] = $targetModule['title'] ?? '';

        // Load raw markdown
        $mdFile = $targetLesson['file'] ?? ($targetLesson['id'] . '.md');
        $fullMdPath = $courseDir . '/' . $mdFile;
        $rawContent = file_exists($fullMdPath) ? file_get_contents($fullMdPath) : '';

        // Separate user markdown body from frontmatter
        $summary = '';
        $videoUrl = '';
        $bodyMarkdown = $rawContent;
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $rawContent, $fmMatches)) {
            $fm = Yaml::parse($fmMatches[1]) ?: [];
            $summary = $fm['summary'] ?? '';
            $videoUrl = $fm['video_url'] ?? '';
            $bodyMarkdown = $fmMatches[2];
        }

        $targetLesson['summary'] = $summary;
        $targetLesson['video_url'] = $videoUrl;
        $targetLesson['raw_markdown'] = $bodyMarkdown;

        // Load quiz YAML
        $quizFile = $targetLesson['quiz_file'] ?? ($targetLesson['id'] . '.yml');
        $fullQuizPath = $courseDir . '/' . $quizFile;
        if (file_exists($fullQuizPath)) {
            $targetLesson['quiz_yaml'] = file_get_contents($fullQuizPath);
            $targetLesson['quiz_data'] = Yaml::parseFile($fullQuizPath) ?: [];
        } else {
            $targetLesson['quiz_yaml'] = '';
            $targetLesson['quiz_data'] = [];
        }

        return $targetLesson;
    }

    /**
     * Saves or creates a lesson and its attached quiz in course.yml and on disk.
     */
    public static function saveLesson(string $projectId, array $project, string $courseId, array $data): bool
    {
        $course = self::getCourse($projectId, $project, $courseId);
        if (!$course) return false;

        $coursesDir = self::getCoursesDir($project);
        $courseDir = $coursesDir . '/' . $course['id'];

        $lessonId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $data['id'] ?? '');
        $moduleId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $data['module_id'] ?? '');
        if (!$lessonId || !$moduleId) return false;

        $type = in_array($data['type'] ?? '', ['reading', 'quiz', 'video']) ? $data['type'] : 'reading';
        $title = trim(strip_tags($data['title'] ?? 'Untitled Lesson'));
        $duration = trim(strip_tags($data['duration'] ?? '15 mins'));
        $videoUrl = trim(strip_tags($data['video_url'] ?? ''));
        $summary = trim(strip_tags($data['summary'] ?? ''));

        $modDir = $courseDir . '/' . $moduleId;
        if (!is_dir($modDir)) {
            @mkdir($modDir, 0777, true);
        }

        $mdRelPath = $moduleId . '/' . $lessonId . '.md';
        $quizRelPath = $moduleId . '/' . $lessonId . '_quiz.yml';

        // 1. Write Markdown file
        $rawMarkdown = $data['markdown'] ?? '';
        if ($type !== 'quiz' || !empty($rawMarkdown)) {
            if (preg_match('/^---\s*\n.*?\n---\s*\n(.*)$/s', $rawMarkdown, $m)) {
                $rawMarkdown = trim($m[1]);
            }
            $fm = "---\ntitle: " . json_encode($title, JSON_UNESCAPED_UNICODE) . "\nduration: \"{$duration}\"\n";
            if ($summary) $fm .= "summary: " . json_encode($summary, JSON_UNESCAPED_UNICODE) . "\n";
            if ($videoUrl) $fm .= "video_url: \"{$videoUrl}\"\n";
            $fm .= "---\n\n";

            self::atomicWriteFile($courseDir . '/' . $mdRelPath, $fm . trim($rawMarkdown));
        }

        // 2. Write Quiz YAML if provided
        $quizFileToRecord = null;
        if (!empty($data['quiz_questions']) && is_array($data['quiz_questions'])) {
            $quizData = [
                'title' => $title . ' Assessment',
                'description' => $data['quiz_description'] ?? 'Test your knowledge on this unit.',
                'passing_score' => max(1, min(100, (int)($data['quiz_passing_score'] ?? 80))),
                'shuffle_questions' => !empty($data['shuffle_questions']),
                'shuffle_options' => !empty($data['shuffle_options']),
                'time_limit_minutes' => max(0, (int)($data['time_limit_minutes'] ?? 0)),
                'max_attempts' => max(0, (int)($data['max_attempts'] ?? 0)),
                'cooldown_minutes' => max(0, (int)($data['cooldown_minutes'] ?? 0)),
                'questions' => $data['quiz_questions'],
            ];
            self::atomicWriteFile($courseDir . '/' . $quizRelPath, Yaml::dump($quizData, 6, 2));
            $quizFileToRecord = $quizRelPath;
        } elseif (!empty($data['quiz_yaml'])) {
            self::atomicWriteFile($courseDir . '/' . $quizRelPath, $data['quiz_yaml']);
            $quizFileToRecord = $quizRelPath;
        }

        // 3. Update course.yml
        $courseYamlPath = $courseDir . '/course.yml';
        $courseYaml = Yaml::parseFile($courseYamlPath) ?: [];
        $modules = $courseYaml['modules'] ?? [];
        $foundModule = false;

        foreach ($modules as &$mod) {
            if ($mod['id'] === $moduleId) {
                $foundModule = true;
                $mod['lessons'] = $mod['lessons'] ?? [];
                $foundLesson = false;
                foreach ($mod['lessons'] as &$lsn) {
                    if ($lsn['id'] === $lessonId) {
                        $lsn['title'] = $title;
                        $lsn['duration'] = $duration;
                        $lsn['type'] = $type;
                        $lsn['file'] = $mdRelPath;
                        if ($quizFileToRecord) {
                            $lsn['quiz_file'] = $quizFileToRecord;
                        }
                        if ($type === 'quiz') {
                            $lsn['passing_score'] = max(1, min(100, (int)($data['quiz_passing_score'] ?? 80)));
                        }
                        $foundLesson = true;
                        break;
                    }
                }
                unset($lsn);

                if (!$foundLesson) {
                    $newLesson = [
                        'id' => $lessonId,
                        'title' => $title,
                        'duration' => $duration,
                        'type' => $type,
                        'file' => $mdRelPath,
                    ];
                    if ($quizFileToRecord) {
                        $newLesson['quiz_file'] = $quizFileToRecord;
                    }
                    if ($type === 'quiz') {
                        $newLesson['passing_score'] = max(1, min(100, (int)($data['quiz_passing_score'] ?? 80)));
                    }
                    $mod['lessons'][] = $newLesson;
                }
                break;
            }
        }
        unset($mod);

        if (!$foundModule) {
            $newEntry = [
                'id' => $lessonId,
                'title' => $title,
                'duration' => $duration,
                'type' => $type,
                'file' => $mdRelPath,
            ];
            if ($quizFileToRecord) {
                $newEntry['quiz_file'] = $quizFileToRecord;
            }
            if ($type === 'quiz') {
                $newEntry['passing_score'] = max(1, min(100, (int)($data['quiz_passing_score'] ?? 80)));
            }
            $modules[] = [
                'id' => $moduleId,
                'title' => ucwords(str_replace(['-', '_'], ' ', $moduleId)),
                'lessons' => [$newEntry]
            ];
        }

        $courseYaml['modules'] = $modules;
        return self::atomicWriteFile($courseYamlPath, Yaml::dump($courseYaml, 6, 2));
    }

    /**
     * Deletes a lesson entry from course.yml and removes associated lesson files.
     */
    public static function deleteLesson(string $projectId, array $project, string $courseId, string $lessonId): bool
    {
        $course = self::getCourse($projectId, $project, $courseId);
        if (!$course) return false;

        $coursesDir = self::getCoursesDir($project);
        $courseDir = $coursesDir . '/' . $course['id'];
        $courseYamlPath = $courseDir . '/course.yml';

        $courseYaml = Yaml::parseFile($courseYamlPath) ?: [];
        $modules = $courseYaml['modules'] ?? [];

        foreach ($modules as &$mod) {
            $filtered = [];
            foreach ($mod['lessons'] ?? [] as $lsn) {
                if ($lsn['id'] === $lessonId) {
                    if (!empty($lsn['file']) && file_exists($courseDir . '/' . $lsn['file'])) {
                        @unlink($courseDir . '/' . $lsn['file']);
                    }
                    if (!empty($lsn['quiz_file']) && file_exists($courseDir . '/' . $lsn['quiz_file'])) {
                        @unlink($courseDir . '/' . $lsn['quiz_file']);
                    }
                } else {
                    $filtered[] = $lsn;
                }
            }
            $mod['lessons'] = $filtered;
        }
        unset($mod);

        $courseYaml['modules'] = $modules;
        return (bool)file_put_contents($courseYamlPath, Yaml::dump($courseYaml, 6, 2));
    }

    /**
     * Generates a pure-PHP SVG QR code representation for an absolute URL.
     */
    public static function generateSvgQrCode(string $url, int $size = 140): string
    {
        return QrCodeGenerator::svg($url, $size);
    }

    /**
     * Generates an RFC 4180-compliant CSV string containing the enrolled student gradebook.
     */
    public static function exportGradebookCsv(string $projectId, array $project, string $courseId): string
    {
        $course = self::getCourse($projectId, $project, $courseId);

        // Fast path: direct streaming from SQLite caching ledger
        if (class_exists(\App\SPPDocs\Services\LmsLedgerService::class)) {
            $ledgerRows = \App\SPPDocs\Services\LmsLedgerService::getGradebookRows($projectId, $courseId, $project);
            if (!empty($ledgerRows)) {
                $out = fopen('php://temp', 'r+');
                fputcsv($out, [
                    'Learner Username',
                    'Course ID',
                    'Course Title',
                    'Lifecycle State',
                    'Progress Percentage',
                    'Average Quiz Score',
                    'Certificate ID',
                    'Last Active'
                ]);
                foreach ($ledgerRows as $row) {
                    fputcsv($out, [
                        $row['username'],
                        $row['course_id'],
                        $course['title'] ?? $row['course_id'],
                        $row['state'],
                        $row['percentage'] . '%',
                        $row['avg_score'] !== null ? round((float)$row['avg_score']) . '%' : 'N/A',
                        $row['certificate_id'] ?? 'N/A',
                        date('Y-m-d H:i', (int)$row['last_accessed_at'])
                    ]);
                }
                rewind($out);
                $csv = stream_get_contents($out);
                fclose($out);
                return $csv;
            }
        }

        $analytics = self::getCourseAnalytics($projectId, $project, $courseId);
        $course = $analytics['course'] ?? self::getCourse($projectId, $project, $courseId);

        $out = fopen('php://temp', 'r+');
        fputcsv($out, [
            'Learner Username',
            'Course ID',
            'Course Title',
            'Lifecycle State',
            'Progress Percentage',
            'Lessons Completed',
            'Total Course Items',
            'Certificate ID',
            'Last Active'
        ]);

        foreach ($analytics['roster'] ?? [] as $r) {
            $prog = self::getUserProgress($projectId, $project, $courseId, $r['username']);
            fputcsv($out, [
                $r['username'],
                $courseId,
                $course['title'] ?? $courseId,
                $r['state'] ?? ($prog['percentage'] >= 100 ? 'certified' : 'in_progress'),
                $prog['percentage'] . '%',
                $prog['completed_count'] ?? count($prog['completed_lessons'] ?? []),
                $prog['total_items'] ?? 0,
                $r['certificate_id'] ?? 'N/A',
                $r['last_accessed'] ?? date('Y-m-d H:i')
            ]);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    private static function recursiveRemoveDir(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? self::recursiveRemoveDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
