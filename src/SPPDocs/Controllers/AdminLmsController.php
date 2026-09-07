<?php

namespace App\SPPDocs\Controllers;

use SPPMod\SPPView\Attributes\Route;
use App\SPPDocs\Traits\ProjectAwareTrait;
use App\SPPDocs\Services\FeatureManager;
use App\SPPDocs\Services\PermissionManager;
use App\SPPDocs\Services\LmsService;
use SPP\App;
use SPP\Response;
use Symfony\Component\Yaml\Yaml;

/**
 * AdminLmsController
 * Teacher & Author Course Studio. Enables creating, editing, and publishing
 * Git-native courses, organizing curriculum modules, authoring quizzes,
 * inspecting student rosters, and reviewing learning analytics.
 */
class AdminLmsController extends \SPPMod\SPPView\ViewController
{
    use ProjectAwareTrait;

    public function __construct()
    {
        $this->loadProjectConfig();
    }

    protected function requireAdminAccess(string $projectId, string $requiredPermission = 'lms.view_studio'): void
    {
        $this->ensureSession();
        $user = $this->getProjectUser();
        $username = $user['username'] ?? null;

        if (!$username) {
            $loginUrl = App::url('login') . '?redirect=' . urlencode(App::url('admin/lms') . '?project=' . $projectId);
            Response::redirect($loginUrl);
            exit;
        }

        $isGlobal = PermissionManager::isGlobalAdmin($username);
        $isProject = PermissionManager::isProjectAdmin($projectId, $username);
        $hasPerm = PermissionManager::canUser($requiredPermission, $projectId, $username)
                || PermissionManager::canUser('lms.manage_courses', $projectId, $username);

        if (!$isGlobal && !$isProject && !$hasPerm && empty($_SESSION['sppdocs_admin_auth'])) {
            http_response_code(403);
            exit("Access Denied: You do not have '{$requiredPermission}' permissions for this project.");
        }
    }

    protected function requireCourseAccess(string $projectId, string $courseId): void
    {
        $this->ensureSession();
        $user = $this->getProjectUser();
        $username = $user['username'] ?? null;

        if (!$username) {
            $loginUrl = App::url('login') . '?redirect=' . urlencode(App::url('admin/lms') . '?project=' . $projectId);
            Response::redirect($loginUrl);
            exit;
        }

        if (!PermissionManager::canUserManageCourse($projectId, $courseId, $username) && empty($_SESSION['sppdocs_admin_auth'])) {
            http_response_code(403);
            exit("Access Denied: You do not have permissions to author or manage this course.");
        }
    }

    /**
     * Course Studio Dashboard: Course listing, status toggles, and scaffolding.
     */
    #[Route('/admin/lms', method: 'GET')]
    public function index($projectId = null)
    {
        $projectId = $this->resolveActiveProjectId($projectId);
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Project not found.']);
        }

        $this->requireAdminAccess($projectId);
        $project = $this->config['projects'][$projectId];
        FeatureManager::requireFeature('lms', $project, $projectId);

        $courses = LmsService::getAllCourses($projectId, $project, true);

        // Attach learner stats to each course
        foreach ($courses as &$c) {
            $c['analytics'] = LmsService::getCourseAnalytics($projectId, $project, $c['id']);
        }
        unset($c);

        return $this->render('admin/lms/index', [
            'project' => $project,
            'project_id' => $projectId,
            'courses' => $courses,
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
        ]);
    }

    /**
     * Edit or Create Course Metadata.
     */
    #[Route('/admin/lms/course/edit', method: 'GET')]
    public function editCourse($projectId = null, $courseId = null)
    {
        $projectId = $this->resolveActiveProjectId($projectId);
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Project not found.']);
        }

        $project = $this->config['projects'][$projectId];

        $courseId = $courseId ?? $_GET['course'] ?? $_GET['courseId'] ?? '';
        if ($courseId) {
            $this->requireCourseAccess($projectId, $courseId);
        } else {
            $this->requireAdminAccess($projectId, 'lms.manage_courses');
        }

        $course = $courseId ? LmsService::getCourse($projectId, $project, $courseId) : null;

        return $this->render('admin/lms/course_form', [
            'project' => $project,
            'project_id' => $projectId,
            'course' => $course,
            'course_id' => $courseId,
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
        ]);
    }

    /**
     * Save Course Metadata.
     */
    #[Route('/admin/lms/course/save', method: 'POST')]
    public function saveCourse()
    {
        $this->ensureSession();
        $this->verifyCsrf();

        $projectId = $this->resolveActiveProjectId($_POST['project_id'] ?? null);
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            Response::redirect(App::url('admin'));
            exit;
        }

        $project = $this->config['projects'][$projectId];

        $courseId = strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['course_id'] ?? ''));
        if (!$courseId) {
            $_SESSION['adm_flash_error'] = 'Course identifier is required.';
            Response::redirect(App::url('admin/lms') . '?project=' . $projectId);
            exit;
        }

        $existingCourse = LmsService::getCourse($projectId, $project, $courseId);
        if ($existingCourse) {
            $this->requireCourseAccess($projectId, $courseId);
        } else {
            $this->requireAdminAccess($projectId, 'lms.manage_courses');
        }

        $courseData = [
            'id' => $courseId,
            'title' => trim(strip_tags($_POST['title'] ?? '')),
            'tagline' => trim(strip_tags($_POST['tagline'] ?? '')),
            'description' => trim($_POST['description'] ?? ''),
            'level' => in_array($_POST['level'] ?? '', ['Beginner', 'Intermediate', 'Advanced']) ? $_POST['level'] : 'Intermediate',
            'category' => trim(strip_tags($_POST['category'] ?? 'General')),
            'duration' => trim(strip_tags($_POST['duration'] ?? '2 hours')),
            'badge_icon' => trim(strip_tags($_POST['badge_icon'] ?? '🎓')),
            'published' => !empty($_POST['published']),
            'progression_mode' => in_array($_POST['progression_mode'] ?? '', ['linear', 'free']) ? $_POST['progression_mode'] : 'free',
            'passing_score' => max(1, min(100, (int)($_POST['passing_score'] ?? 80))),
            'certificate_enabled' => !empty($_POST['certificate_enabled']),
            'instructor' => [
                'name' => trim(strip_tags($_POST['instructor_name'] ?? '')),
                'title' => trim(strip_tags($_POST['instructor_title'] ?? '')),
                'bio' => trim(strip_tags($_POST['instructor_bio'] ?? '')),
            ],
            'prerequisites' => array_filter(array_map('trim', explode("\n", $_POST['prerequisites'] ?? ''))),
        ];

        LmsService::saveCourse($projectId, $project, $courseData);

        $_SESSION['adm_flash_success'] = "Course '{$courseData['title']}' saved successfully.";
        Response::redirect(App::url('admin/lms') . '?project=' . $projectId);
    }

    /**
     * Delete Course.
     */
    #[Route('/admin/lms/course/delete', method: 'POST')]
    public function deleteCourse()
    {
        $this->ensureSession();
        $this->verifyCsrf();

        $projectId = $this->resolveActiveProjectId($_POST['project_id'] ?? null);
        $courseId = $_POST['course_id'] ?? '';

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            Response::redirect(App::url('admin'));
            exit;
        }

        $this->requireAdminAccess($projectId, 'lms.manage_courses');
        $project = $this->config['projects'][$projectId];

        LmsService::deleteCourse($projectId, $project, $courseId);

        $_SESSION['adm_flash_success'] = "Course deleted successfully.";
        Response::redirect(App::url('admin/lms') . '?project=' . $projectId);
    }

    /**
     * Student Roster & Learning Analytics.
     */
    #[Route('/admin/lms/analytics', method: 'GET')]
    public function analytics($projectId = null, $courseId = null)
    {
        $projectId = $this->resolveActiveProjectId($projectId);
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Project not found.']);
        }

        $this->requireAdminAccess($projectId, 'lms.view_roster');
        $project = $this->config['projects'][$projectId];

        $courses = LmsService::getAllCourses($projectId, $project, true);
        $activeCourseId = $courseId ?? $_GET['course'] ?? ($courses[0]['id'] ?? '');

        $analytics = $activeCourseId ? LmsService::getCourseAnalytics($projectId, $project, $activeCourseId) : [];

        return $this->render('admin/lms/analytics', [
            'project' => $project,
            'project_id' => $projectId,
            'courses' => $courses,
            'active_course_id' => $activeCourseId,
            'analytics' => $analytics,
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
        ]);
    }

    /**
     * One-Click Sample Course Generator.
     */
    #[Route('/admin/lms/scaffold', method: 'POST')]
    public function scaffoldSample()
    {
        $this->ensureSession();
        $this->verifyCsrf();

        $projectId = $this->resolveActiveProjectId($_POST['project_id'] ?? null);
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            Response::redirect(App::url('admin'));
            exit;
        }

        $this->requireAdminAccess($projectId, 'lms.manage_courses');
        $project = $this->config['projects'][$projectId];

        $courseId = LmsService::scaffoldSampleCourse($projectId, $project);

        $_SESSION['adm_flash_success'] = "Sample course 'Mastering SPP Framework' scaffolded successfully with modules, lessons, and interactive quizzes!";
        Response::redirect(App::url('admin/lms') . '?project=' . $projectId);
    }

    /**
     * Edit or Create Lesson & Quiz.
     */
    #[Route('/admin/lms/lesson/edit', method: 'GET')]
    public function editLesson($projectId = null, $courseId = null, $lessonId = null)
    {
        $projectId = $this->resolveActiveProjectId($projectId);
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Project not found.']);
        }

        $courseId = $courseId ?? $_GET['course'] ?? $_GET['courseId'] ?? '';
        $lessonId = $lessonId ?? $_GET['lesson'] ?? $_GET['lessonId'] ?? '';
        $moduleId = $_GET['module'] ?? $_GET['moduleId'] ?? '';

        $this->requireCourseAccess($projectId, $courseId);
        $project = $this->config['projects'][$projectId];

        $course = LmsService::getCourse($projectId, $project, $courseId);
        if (!$course) {
            return $this->render('errors/404', ['message' => 'Course not found.']);
        }

        $lesson = $lessonId ? LmsService::getLessonRaw($projectId, $project, $courseId, $lessonId) : null;
        if (!$moduleId && $lesson) {
            $moduleId = $lesson['module_id'] ?? '';
        }
        if (!$moduleId && !empty($course['modules'][0]['id'])) {
            $moduleId = $course['modules'][0]['id'];
        }

        return $this->render('admin/lms/lesson_form', [
            'project' => $project,
            'project_id' => $projectId,
            'course' => $course,
            'course_id' => $courseId,
            'lesson' => $lesson,
            'lesson_id' => $lessonId,
            'module_id' => $moduleId,
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
        ]);
    }

    /**
     * Save Lesson and Quiz Content.
     */
    #[Route('/admin/lms/lesson/save', method: 'POST')]
    public function saveLesson()
    {
        $this->ensureSession();
        $this->verifyCsrf();

        $projectId = $this->resolveActiveProjectId($_POST['project_id'] ?? null);
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            Response::redirect(App::url('admin'));
            exit;
        }

        $courseId = strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['course_id'] ?? ''));
        $lessonId = strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['lesson_id'] ?? ''));
        $moduleId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['module_id'] ?? '');

        if (!$courseId || !$lessonId || !$moduleId) {
            $_SESSION['adm_flash_error'] = 'Course, Module, and Lesson identifiers are required.';
            Response::redirect(App::url('admin/lms') . '?project=' . $projectId);
            exit;
        }

        $this->requireCourseAccess($projectId, $courseId);
        $project = $this->config['projects'][$projectId];

        // Process visual quiz questions if submitted
        $quizQuestions = [];
        if (!empty($_POST['questions']) && is_array($_POST['questions'])) {
            foreach ($_POST['questions'] as $q) {
                $qid = trim($q['id'] ?? '') ?: ('q_' . uniqid());
                $qType = $q['type'] ?? 'multiple_choice';
                $prompt = trim($q['prompt'] ?? '');
                if (!$prompt) continue;

                $builtQ = [
                    'id' => $qid,
                    'type' => $qType,
                    'prompt' => $prompt,
                    'explanation' => trim($q['explanation'] ?? ''),
                ];

                if ($qType === 'multiple_choice' || $qType === 'multi_select') {
                    $options = [];
                    foreach ($q['options'] ?? [] as $optIdx => $opt) {
                        $optText = trim($opt['text'] ?? '');
                        if (!$optText) continue;
                        $options[] = [
                            'id' => trim($opt['id'] ?? '') ?: chr(97 + $optIdx),
                            'text' => $optText,
                            'correct' => !empty($opt['correct']),
                        ];
                    }
                    $builtQ['options'] = $options;
                } elseif ($qType === 'code_check') {
                    $builtQ['code'] = trim($q['code'] ?? '');
                    $builtQ['expected'] = trim($q['expected'] ?? '');
                    $builtQ['hint'] = trim($q['hint'] ?? '');
                }

                $quizQuestions[] = $builtQ;
            }
        }

        $lessonData = [
            'id' => $lessonId,
            'module_id' => $moduleId,
            'title' => trim(strip_tags($_POST['title'] ?? '')),
            'type' => in_array($_POST['type'] ?? '', ['reading', 'quiz', 'video']) ? $_POST['type'] : 'reading',
            'duration' => trim(strip_tags($_POST['duration'] ?? '15 mins')),
            'summary' => trim(strip_tags($_POST['summary'] ?? '')),
            'video_url' => trim(strip_tags($_POST['video_url'] ?? '')),
            'markdown' => $_POST['markdown'] ?? '',
            'quiz_description' => trim($_POST['quiz_description'] ?? ''),
            'quiz_passing_score' => (int)($_POST['quiz_passing_score'] ?? 80),
            'shuffle_questions' => !empty($_POST['shuffle_questions']),
            'shuffle_options' => !empty($_POST['shuffle_options']),
            'time_limit_minutes' => (int)($_POST['time_limit_minutes'] ?? 0),
            'max_attempts' => (int)($_POST['max_attempts'] ?? 0),
            'cooldown_minutes' => (int)($_POST['cooldown_minutes'] ?? 0),
            'quiz_questions' => $quizQuestions,
        ];

        LmsService::saveLesson($projectId, $project, $courseId, $lessonData);

        $_SESSION['adm_flash_success'] = "Lesson '{$lessonData['title']}' saved successfully.";
        Response::redirect(App::url('admin/lms/course/edit') . '?project=' . $projectId . '&course=' . $courseId);
    }

    /**
     * Delete Lesson.
     */
    #[Route('/admin/lms/lesson/delete', method: 'POST')]
    public function deleteLesson()
    {
        $this->ensureSession();
        $this->verifyCsrf();

        $projectId = $this->resolveActiveProjectId($_POST['project_id'] ?? null);
        $courseId = $_POST['course_id'] ?? '';
        $lessonId = $_POST['lesson_id'] ?? '';

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            Response::redirect(App::url('admin'));
            exit;
        }

        $this->requireCourseAccess($projectId, $courseId);
        $project = $this->config['projects'][$projectId];

        LmsService::deleteLesson($projectId, $project, $courseId, $lessonId);

        $_SESSION['adm_flash_success'] = "Lesson deleted successfully.";
        Response::redirect(App::url('admin/lms/course/edit') . '?project=' . $projectId . '&course=' . $courseId);
    }

    /**
     * Export Student Gradebook as CSV.
     */
    #[Route('/admin/lms/export', method: 'GET')]
    public function exportGradebook($projectId = null, $courseId = null)
    {
        $projectId = $this->resolveActiveProjectId($projectId);
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Project not found.']);
        }

        $this->requireAdminAccess($projectId, 'lms.export_gradebook');
        $project = $this->config['projects'][$projectId];

        $courseId = $courseId ?? $_GET['course'] ?? $_GET['courseId'] ?? '';
        $course = LmsService::getCourse($projectId, $project, $courseId);

        if (!$course) {
            return $this->render('errors/404', ['message' => 'Course not found.']);
        }

        $csvContent = LmsService::exportGradebookCsv($projectId, $project, $courseId);
        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $courseId) . '_gradebook_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
        echo $csvContent;
        exit;
    }
}
