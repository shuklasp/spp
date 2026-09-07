<?php

namespace App\SPPDocs\Controllers;

use SPPMod\SPPView\Attributes\Route;
use App\SPPDocs\Traits\ProjectAwareTrait;
use App\SPPDocs\Services\FeatureManager;
use App\SPPDocs\Services\LmsService;
use SPP\App;
use SPP\Response;

/**
 * LmsController
 * Student-facing learning management portal. Handles course catalog, interactive syllabus,
 * distraction-free lesson player, HTMX instant quiz evaluation, learner dashboards,
 * and verifiable certificate generation.
 */
class LmsController extends \SPPMod\SPPView\ViewController
{
    use ProjectAwareTrait;

    public function __construct()
    {
        $this->loadProjectConfig();
    }

    /**
     * Course Catalog: Browse and filter available courses.
     */
    #[Route('/lms', method: 'GET')]
    #[Route('/courses', method: 'GET')]
    public function catalog($projectId = null)
    {
        $projectId = $this->resolveActiveProjectId($projectId);
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Project not found.']);
        }

        $project = $this->config['projects'][$projectId];
        FeatureManager::requireFeature('lms', $project, $projectId);
        $this->ensureSession();

        $user = $this->getProjectUser();
        $username = $user['username'] ?? 'guest';

        $allCourses = LmsService::getAllCourses($projectId, $project, false);

        // Filters
        $filterLevel = trim($_GET['level'] ?? '');
        $searchQuery = trim($_GET['search'] ?? $_GET['q'] ?? '');

        $courses = array_filter($allCourses, function ($c) use ($filterLevel, $searchQuery) {
            if ($filterLevel && strcasecmp($c['level'] ?? '', $filterLevel) !== 0) {
                return false;
            }
            if ($searchQuery) {
                $matchTitle = stripos($c['title'] ?? '', $searchQuery) !== false;
                $matchDesc = stripos($c['description'] ?? '', $searchQuery) !== false;
                $matchTag = stripos($c['tagline'] ?? '', $searchQuery) !== false;
                if (!$matchTitle && !$matchDesc && !$matchTag) {
                    return false;
                }
            }
            return true;
        });

        // Attach user progress to each course
        foreach ($courses as &$course) {
            if ($username !== 'guest') {
                $course['user_progress'] = LmsService::getUserProgress($projectId, $project, $course['id'], $username);
            } else {
                $course['user_progress'] = ['percentage' => 0, 'completed_count' => 0, 'state' => 'unregistered'];
            }
        }
        unset($course);

        return $this->render('lms/catalog', [
            'project' => $project,
            'project_id' => $projectId,
            'courses' => $courses,
            'filter_level' => $filterLevel,
            'search_query' => $searchQuery,
            'user' => $user,
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
        ]);
    }

    /**
     * Course Syllabus: Overview, modules, prerequisites, and enrollment.
     */
    #[Route('/lms/course', method: 'GET')]
    public function course($projectId = null, $courseId = null)
    {
        $projectId = $this->resolveActiveProjectId($projectId);
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Project not found.']);
        }

        $project = $this->config['projects'][$projectId];
        FeatureManager::requireFeature('lms', $project, $projectId);
        $this->ensureSession();

        $courseId = $courseId ?? $_GET['course'] ?? $_GET['courseId'] ?? '';
        $course = LmsService::getCourse($projectId, $project, $courseId);

        if (!$course) {
            return $this->render('errors/404', ['message' => 'Course not found.']);
        }

        $user = $this->getProjectUser();
        $username = $user['username'] ?? 'guest';
        $progress = ($username !== 'guest')
            ? LmsService::getUserProgress($projectId, $project, $course['id'], $username)
            : ['percentage' => 0, 'completed_lessons' => [], 'state' => 'unregistered'];

        // Determine first incomplete lesson for Resume CTA
        $firstIncompleteLesson = null;
        foreach ($course['modules'] as $mod) {
            foreach ($mod['lessons'] ?? [] as $lsn) {
                if (!in_array($lsn['id'], $progress['completed_lessons'] ?? [])) {
                    $firstIncompleteLesson = $lsn;
                    break 2;
                }
            }
        }
        if (!$firstIncompleteLesson && !empty($course['modules'][0]['lessons'][0])) {
            $firstIncompleteLesson = $course['modules'][0]['lessons'][0];
        }

        return $this->render('lms/course', [
            'project' => $project,
            'project_id' => $projectId,
            'course' => $course,
            'progress' => $progress,
            'first_lesson' => $firstIncompleteLesson,
            'user' => $user,
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
        ]);
    }

    /**
     * Focus Lesson Viewer: Distraction-free reader with drawer and quizzes.
     */
    #[Route('/lms/lesson', method: 'GET')]
    public function lesson($projectId = null, $courseId = null, $lessonId = null)
    {
        $projectId = $this->resolveActiveProjectId($projectId);
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Project not found.']);
        }

        $project = $this->config['projects'][$projectId];
        FeatureManager::requireFeature('lms', $project, $projectId);
        $this->ensureSession();

        $courseId = $courseId ?? $_GET['course'] ?? $_GET['courseId'] ?? '';
        $lessonId = $lessonId ?? $_GET['lesson'] ?? $_GET['lessonId'] ?? '';

        $course = LmsService::getCourse($projectId, $project, $courseId);
        if (!$course) {
            return $this->render('errors/404', ['message' => 'Course not found.']);
        }

        // If lesson ID is omitted, default to first lesson of the course
        if (!$lessonId && !empty($course['modules'][0]['lessons'][0]['id'])) {
            $lessonId = $course['modules'][0]['lessons'][0]['id'];
        }

        $lesson = LmsService::getLesson($projectId, $project, $courseId, $lessonId);
        if (!$lesson) {
            return $this->render('errors/404', ['message' => 'Lesson not found.']);
        }

        $user = $this->getProjectUser();
        $username = $user['username'] ?? 'guest';

        // Progression guard: enforce linear sequencing when course is configured with progression_mode: linear
        $canAccess = LmsService::canAccessLesson($projectId, $project, $courseId, $lessonId, $username);
        if (!$canAccess) {
            $nextLesson = LmsService::getNextEligibleLesson($projectId, $project, $courseId, $username);
            if ($nextLesson && $nextLesson['id'] !== $lessonId) {
                $_SESSION['lms_flash_notice'] = "This unit is locked. Please complete previous lessons or assessments to unlock.";
                Response::redirect(App::url('lms/lesson') . '?projectId=' . $projectId . '&course=' . $courseId . '&lesson=' . $nextLesson['id']);
                exit;
            }
        }

        $progress = ($username !== 'guest')
            ? LmsService::getUserProgress($projectId, $project, $course['id'], $username)
            : ['percentage' => 0, 'completed_lessons' => [], 'quiz_attempts' => []];

        $isCompleted = in_array($lesson['id'], $progress['completed_lessons'] ?? []);
        $quizAttempt = $progress['quiz_attempts'][$lesson['id']] ?? null;
        $quizStatus = !empty($lesson['quiz_data']) ? LmsService::getQuizStatus($lesson['quiz_data'], $quizAttempt) : null;

        // HTMX Drawer request only
        if ($this->isHtmx() && isset($_GET['drawer_only'])) {
            return $this->renderPartial('lms/partials/curriculum_drawer', [
                'project_id' => $projectId,
                'course' => $course,
                'current_lesson_id' => $lesson['id'],
                'progress' => $progress,
                'username' => $username,
            ]);
        }

        return $this->render('lms/lesson', [
            'project' => $project,
            'project_id' => $projectId,
            'course' => $course,
            'lesson' => $lesson,
            'progress' => $progress,
            'is_completed' => $isCompleted,
            'quiz_attempt' => $quizAttempt,
            'quiz_status' => $quizStatus,
            'user' => $user,
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
        ]);
    }

    /**
     * HTMX Action: Toggle lesson completion.
     */
    #[Route('/lms/lesson/toggle', method: 'POST')]
    public function toggleLesson()
    {
        $this->ensureSession();
        $this->verifyCsrf();

        $projectId = $_POST['project_id'] ?? '';
        $courseId = $_POST['course_id'] ?? '';
        $lessonId = $_POST['lesson_id'] ?? '';
        $action = $_POST['action'] ?? 'complete';

        $projectId = $this->resolveActiveProjectId($projectId);
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            Response::json(['error' => 'Invalid project'], 404);
            return;
        }

        $project = $this->config['projects'][$projectId];
        $user = $this->getProjectUser();
        $username = $user['username'] ?? 'guest';

        if ($action === 'incomplete') {
            $updatedProg = LmsService::markLessonIncomplete($projectId, $project, $courseId, $lessonId, $username);
            $isCompleted = false;
        } else {
            $updatedProg = LmsService::markLessonComplete($projectId, $project, $courseId, $lessonId, $username);
            $isCompleted = true;

            // Check for certificate minting
            if ($updatedProg['percentage'] >= 100) {
                LmsService::checkAndIssueCertificate($projectId, $project, $courseId, $username, $user['name'] ?? $username);
            }
        }

        $course = LmsService::getCourse($projectId, $project, $courseId);
        $lesson = LmsService::getLesson($projectId, $project, $courseId, $lessonId);

        // Return the updated completion button partial
        return $this->renderPartial('lms/partials/lesson_complete_btn', [
            'project_id' => $projectId,
            'course' => $course,
            'lesson_id' => $lessonId,
            'is_completed' => $isCompleted,
            'progress' => $updatedProg,
            'next_lesson' => $lesson['next_lesson'] ?? null,
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
            'min_watch_percent' => (int)($lesson['frontmatter']['min_watch_percent'] ?? 0),
        ]);
    }

    /**
     * HTMX Action: Submit & Grade Quiz.
     */
    #[Route('/lms/quiz/submit', method: 'POST')]
    public function submitQuiz()
    {
        $this->ensureSession();
        $this->verifyCsrf();

        $projectId = $_POST['project_id'] ?? '';
        $courseId = $_POST['course_id'] ?? '';
        $lessonId = $_POST['lesson_id'] ?? '';
        $submittedAnswers = $_POST['answers'] ?? [];

        $projectId = $this->resolveActiveProjectId($projectId);
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            Response::json(['error' => 'Invalid project'], 404);
            return;
        }

        $project = $this->config['projects'][$projectId];
        $course = LmsService::getCourse($projectId, $project, $courseId);
        $lesson = LmsService::getLesson($projectId, $project, $courseId, $lessonId);

        if (!$course || !$lesson || empty($lesson['quiz_data'])) {
            Response::json(['error' => 'Quiz not found'], 404);
            return;
        }

        $quiz = $lesson['quiz_data'];
        $gradeResult = LmsService::gradeQuiz($quiz, $submittedAnswers);

        $user = $this->getProjectUser();
        $username = $user['username'] ?? 'guest';

        $updatedProg = LmsService::recordQuizAttempt($projectId, $project, $courseId, $lessonId, $username, $gradeResult);

        // Check if certificate can be issued
        $certificate = null;
        if ($updatedProg['percentage'] >= 100) {
            $certificate = LmsService::checkAndIssueCertificate($projectId, $project, $courseId, $username, $user['name'] ?? $username);
        }

        return $this->renderPartial('lms/partials/quiz_result', [
            'project_id' => $projectId,
            'course' => $course,
            'lesson' => $lesson,
            'result' => $gradeResult,
            'progress' => $updatedProg,
            'certificate' => $certificate,
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
        ]);
    }

    /**
     * Student Dashboard ("My Learning")
     */
    #[Route('/lms/dashboard', method: 'GET')]
    #[Route('/lms/my-learning', method: 'GET')]
    public function dashboard($projectId = null)
    {
        $projectId = $this->resolveActiveProjectId($projectId);
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Project not found.']);
        }

        $project = $this->config['projects'][$projectId];
        FeatureManager::requireFeature('lms', $project, $projectId);
        $this->ensureSession();

        $user = $this->getProjectUser();
        $username = $user['username'] ?? null;

        if (!$username) {
            header("Location: " . App::url('login') . "?redirect=" . urlencode(App::url('lms/dashboard') . '?projectId=' . $projectId));
            exit;
        }

        $dashboardData = LmsService::getUserDashboard($projectId, $project, $username);

        return $this->render('lms/dashboard', [
            'project' => $project,
            'project_id' => $projectId,
            'dashboard' => $dashboardData,
            'user' => $user,
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
        ]);
    }

    /**
     * Certificate of Completion: High-resolution print & SVG view.
     */
    #[Route('/lms/certificate', method: 'GET')]
    public function certificate($projectId = null, $certId = null)
    {
        $projectId = $this->resolveActiveProjectId($projectId);
        $certId = $certId ?? $_GET['code'] ?? $_GET['id'] ?? '';

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Project not found.']);
        }

        $project = $this->config['projects'][$projectId];
        $cert = LmsService::getCertificate($projectId, $project, $certId);

        if (!$cert) {
            // Fallback search across all projects
            $cert = LmsService::findCertificateByCode($certId);
        }

        $verifyUrl = $cert['verification_url'] ?? (App::url('lms/verify') . '?code=' . $cert['certificate_id']);
        $qrCodeSvg = LmsService::generateSvgQrCode($verifyUrl, 110);

        $linkedInUrl = 'https://www.linkedin.com/profile/add?' . http_build_query([
            'startTask' => 'CERTIFICATION_NAME',
            'name' => $cert['course_title'] ?? 'SPP Framework Masterclass',
            'organizationName' => $project['title'] ?? 'SPP Framework',
            'issueYear' => date('Y', $cert['issued_at'] ?? time()),
            'issueMonth' => date('n', $cert['issued_at'] ?? time()),
            'certUrl' => $verifyUrl,
            'certId' => $cert['certificate_id'] ?? '',
        ]);

        return $this->render('lms/certificate', [
            'project' => $project,
            'project_id' => $projectId,
            'certificate' => $cert,
            'qr_code_svg' => $qrCodeSvg,
            'linkedin_url' => $linkedInUrl,
        ]);
    }

    /**
     * Public Certificate Verification: Accessible without authentication.
     */
    #[Route('/lms/verify', method: 'GET')]
    public function verifyCertificate()
    {
        $code = trim($_GET['code'] ?? $_GET['id'] ?? '');
        $cert = null;

        if ($code) {
            $cert = LmsService::findCertificateByCode($code);
        }

        return $this->render('lms/verify', [
            'code' => $code,
            'certificate' => $cert,
        ]);
    }
}
