<?php

namespace App\SPPDocs\Services;

use App\SPPDocs\Contracts\IssueTrackerDriverInterface;
use App\SPPDocs\Contracts\ForumDriverInterface;
use App\SPPDocs\Drivers\Issues\InternalIssueDriver;
use App\SPPDocs\Drivers\Issues\GitHubIssueDriver;
use App\SPPDocs\Drivers\Issues\ExternalLinkDriver;
use App\SPPDocs\Drivers\Forums\InternalForumDriver;
use App\SPPDocs\Drivers\Forums\ExternalForumDriver;
use SPP\App;
use SPP\Response;
use Symfony\Component\Yaml\Yaml;

/**
 * FeatureManager
 * Manages modular feature flags, driver resolution, and navigation across SPPDocs.
 */
class FeatureManager
{
    private static ?array $globalConfig = null;

    /**
     * Default feature toggles when not explicitly configured in project.yml or global config.
     */
    private static array $defaultFeatures = [
        'docs'         => ['enabled' => true],
        'blog'         => ['enabled' => false],
        'book_export'  => ['enabled' => true],
        'issues'       => ['enabled' => false, 'driver' => 'internal'],
        'milestones'   => ['enabled' => false],
        'pm_dashboard' => ['enabled' => false],
        'forums'       => ['enabled' => false, 'driver' => 'internal'],
        'views'        => ['enabled' => false],
        'taxonomy'     => ['enabled' => false],
        'blocks'       => ['enabled' => false],
        'modules'      => ['enabled' => false],
        'schemas'      => ['enabled' => false],
        'media'        => ['enabled' => true],
        'openapi'      => ['enabled' => false],
        'i18n'         => ['enabled' => false],
        'automations'  => ['enabled' => false],
        'webhooks'     => ['enabled' => false],
        'lms'          => ['enabled' => false],
    ];

    /**
     * Get the full catalog of modular features organized by tier.
     */
    public static function getFeatureCatalog(): array
    {
        return [
            'content' => [
                'title' => 'Core Content & Publishing',
                'description' => 'Documentation reader, blog articles, and print export generation.',
                'icon' => '📖',
                'features' => [
                    'docs' => [
                        'name' => 'Documentation Engine',
                        'icon' => '📖',
                        'desc' => 'Markdown navigation, full-text search indexer, versioning, and callouts.',
                        'required' => true,
                    ],
                    'blog' => [
                        'name' => 'Blog & Announcements',
                        'icon' => '📰',
                        'desc' => 'Changelog posts, release announcements, and author attributions.',
                    ],
                    'book_export' => [
                        'name' => 'Full-Book Publisher & PDF',
                        'icon' => '📕',
                        'desc' => 'Compile documentation hierarchy into paginated, print-ready document or PDF.',
                    ],
                ],
            ],
            'collaboration' => [
                'title' => 'Project Collaboration & Tracking',
                'description' => 'Integrated issue tracker, agile sprint boards, velocity metrics, and discussion forums.',
                'icon' => '🎯',
                'features' => [
                    'issues' => [
                        'name' => 'Issue Tracker & Kanban Board',
                        'icon' => '🎯',
                        'desc' => 'Linear-grade issue tracking, Kanban drag-and-drop, due dates, and time logs.',
                        'has_driver' => true,
                    ],
                    'milestones' => [
                        'name' => 'Milestones & Sprint Planning',
                        'icon' => '🏁',
                        'desc' => 'Sprint deadlines, progress fraction calculations, and issue grouping.',
                    ],
                    'pm_dashboard' => [
                        'name' => 'PM Dashboard & Strategic Roadmap',
                        'icon' => '📊',
                        'desc' => 'Velocity metrics, completion status, priority breakdowns, and roadmap view.',
                    ],
                    'forums' => [
                        'name' => 'Community Discussions & Forums',
                        'icon' => '💬',
                        'desc' => 'Threaded discussion categories, accepted answers, and emoji reactions.',
                        'has_driver' => true,
                    ],
                ],
            ],
            'studio' => [
                'title' => 'Advanced Content Studio & Modeling',
                'description' => 'Dynamic query builders, taxonomy trees, pluggable layout blocks, and extension modules.',
                'icon' => '🎨',
                'features' => [
                    'views' => [
                        'name' => 'Dynamic Views Studio',
                        'icon' => '🔍',
                        'desc' => 'Visual query builder across Markdown collections with card, table, or list displays.',
                    ],
                    'taxonomy' => [
                        'name' => 'Taxonomy & Vocabularies',
                        'icon' => '🏷️',
                        'desc' => 'Hierarchical category trees, term metadata, colored badges, and term archive pages.',
                    ],
                    'blocks' => [
                        'name' => 'Theme Regions & Pluggable Blocks',
                        'icon' => '🧱',
                        'desc' => 'Place custom HTML blocks, view displays, or widgets into theme regions with visibility rules.',
                    ],
                    'modules' => [
                        'name' => 'App Modules & Extension Hooks',
                        'icon' => '🧩',
                        'desc' => 'Modular extension architecture with kernel alter hooks and lifecycle event triggers.',
                    ],
                    'schemas' => [
                        'name' => 'Content Type Schemas',
                        'icon' => '📐',
                        'desc' => 'Define custom frontmatter fields, validation constraints, and role permissions.',
                    ],
                    'media' => [
                        'name' => 'Media & Image Styles',
                        'icon' => '🖼️',
                        'desc' => 'Asset manager with automated thumbnail and medium image style transformation pipelines.',
                    ],
                ],
            ],
            'developer' => [
                'title' => 'Developer & API Integration',
                'description' => 'Interactive API explorers, translation hubs, and automated CI/CD webhooks.',
                'icon' => '⚡',
                'features' => [
                    'openapi' => [
                        'name' => 'Interactive OpenAPI Explorer',
                        'icon' => '⚡',
                        'desc' => 'Interactive Swagger/Redoc UI with live parameter testing against API endpoints.',
                    ],
                    'i18n' => [
                        'name' => 'Multi-Language (i18n) Hub',
                        'icon' => '🌐',
                        'desc' => 'Language switcher, translation dictionary sync, and localized doc routing.',
                    ],
                    'webhooks' => [
                        'name' => 'Git & Outbox Webhooks',
                        'icon' => '🪝',
                        'desc' => 'Inbound GitHub/GitLab sync triggers and outbound transactional webhooks.',
                    ],
                    'automations' => [
                        'name' => 'CI/CD Automations',
                        'icon' => '⚙️',
                        'desc' => 'Automated linting, link checking, and deployment pipelines.',
                    ],
                ],
            ],
            'education' => [
                'title' => 'Academy & Learning Management (LMS)',
                'description' => 'Interactive courses, curriculum modules, lessons, quizzes, progress tracking, and verifiable certificates.',
                'icon' => '🎓',
                'features' => [
                    'lms' => [
                        'name' => 'Learning Management System',
                        'icon' => '🎓',
                        'desc' => 'Turn project docs into interactive courses with structured lessons, video embeds, coding exercises, multi-format quizzes, student progress, and verifiable certificates.',
                    ],
                ],
            ],
        ];
    }

    /**
     * Built-in configuration presets.
     */
    public static function getBuiltinPresets(): array
    {
        return [
            'minimal_docs' => [
                'title' => 'Minimal Documentation',
                'icon' => '📖',
                'description' => 'Clean documentation site with full-text search. All collaboration, CMS, and studio tools disabled for zero clutter.',
                'features' => [
                    'docs' => true,
                    'blog' => false,
                    'book_export' => true,
                    'issues' => false,
                    'milestones' => false,
                    'pm_dashboard' => false,
                    'forums' => false,
                    'views' => false,
                    'taxonomy' => false,
                    'blocks' => false,
                    'modules' => false,
                    'schemas' => false,
                    'media' => true,
                    'openapi' => false,
                    'i18n' => false,
                    'automations' => false,
                    'webhooks' => false,
                ],
            ],
            'docs_pm' => [
                'title' => 'Docs & Project Management',
                'icon' => '🎯',
                'description' => 'Documentation coupled with full issue tracking, Kanban sprint board, milestones, and strategic roadmap.',
                'features' => [
                    'docs' => true,
                    'blog' => false,
                    'book_export' => true,
                    'issues' => true,
                    'milestones' => true,
                    'pm_dashboard' => true,
                    'forums' => false,
                    'views' => false,
                    'taxonomy' => false,
                    'blocks' => false,
                    'modules' => false,
                    'schemas' => false,
                    'media' => true,
                    'openapi' => false,
                    'i18n' => false,
                    'automations' => true,
                    'webhooks' => true,
                ],
            ],
            'api_developer' => [
                'title' => 'Developer & API Portal',
                'icon' => '⚡',
                'description' => 'Interactive OpenAPI explorer, multi-language hub, webhooks, and CI/CD automations for API-centric projects.',
                'features' => [
                    'docs' => true,
                    'blog' => true,
                    'book_export' => true,
                    'issues' => false,
                    'milestones' => false,
                    'pm_dashboard' => false,
                    'forums' => false,
                    'views' => false,
                    'taxonomy' => false,
                    'blocks' => false,
                    'modules' => false,
                    'schemas' => false,
                    'media' => true,
                    'openapi' => true,
                    'i18n' => true,
                    'automations' => true,
                    'webhooks' => true,
                ],
            ],
            'full_portal' => [
                'title' => 'Full Enterprise Portal',
                'icon' => '🚀',
                'description' => 'Complete suite enabled: Dynamic Views Studio, Taxonomy, Pluggable Blocks, Modules, Schemas, PM, and Community Forums.',
                'features' => [
                    'docs' => true,
                    'blog' => true,
                    'book_export' => true,
                    'issues' => true,
                    'milestones' => true,
                    'pm_dashboard' => true,
                    'forums' => true,
                    'views' => true,
                    'taxonomy' => true,
                    'blocks' => true,
                    'modules' => true,
                    'schemas' => true,
                    'media' => true,
                    'openapi' => true,
                    'i18n' => true,
                    'automations' => true,
                    'webhooks' => true,
                    'lms' => true,
                ],
            ],
            'academy_lms' => [
                'title' => 'Docs & Learning Academy',
                'icon' => '🎓',
                'description' => 'Documentation engine coupled with full interactive courses, quizzes, student progress, and verifiable certificates.',
                'features' => [
                    'docs' => true,
                    'blog' => true,
                    'book_export' => true,
                    'issues' => false,
                    'milestones' => false,
                    'pm_dashboard' => false,
                    'forums' => true,
                    'views' => false,
                    'taxonomy' => true,
                    'blocks' => true,
                    'modules' => false,
                    'schemas' => false,
                    'media' => true,
                    'openapi' => false,
                    'i18n' => false,
                    'automations' => false,
                    'webhooks' => false,
                    'lms' => true,
                ],
            ],
        ];
    }

    /**
     * File path where custom presets are persisted.
     */
    public static function getPresetsFile(): string
    {
        return dirname(__DIR__) . '/etc/feature_presets.yml';
    }

    /**
     * Retrieve all saved custom presets.
     */
    public static function getCustomPresets(): array
    {
        $file = self::getPresetsFile();
        if (file_exists($file)) {
            $data = Yaml::parseFile($file) ?: [];
            return is_array($data) ? $data : [];
        }
        return [];
    }

    /**
     * Retrieve all presets (built-in merged with custom presets).
     */
    public static function getAllPresets(): array
    {
        $presets = self::getBuiltinPresets();
        $custom = self::getCustomPresets();
        foreach ($custom as $key => $preset) {
            $preset['is_custom'] = true;
            $presets[$key] = $preset;
        }
        return $presets;
    }

    /**
     * Save a user-defined custom feature preset.
     */
    public static function saveCustomPreset(string $key, array $presetData): bool
    {
        $key = preg_replace('/[^a-z0-9_\-]/i', '_', strtolower(trim($key)));
        if (!$key) return false;

        $custom = self::getCustomPresets();
        $custom[$key] = [
            'title' => htmlspecialchars($presetData['title'] ?? $presetData['label'] ?? ucfirst(str_replace('_', ' ', $key))),
            'icon' => $presetData['icon'] ?? '⚙️',
            'description' => htmlspecialchars($presetData['description'] ?? 'Custom feature configuration preset'),
            'features' => $presetData['features'] ?? [],
            'created_at' => time(),
        ];

        $file = self::getPresetsFile();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return (bool)file_put_contents($file, Yaml::dump($custom, 4, 2));
    }

    /**
     * Delete a user-defined custom preset.
     */
    public static function deleteCustomPreset(string $key): bool
    {
        $custom = self::getCustomPresets();
        if (isset($custom[$key])) {
            unset($custom[$key]);
            $file = self::getPresetsFile();
            return (bool)file_put_contents($file, Yaml::dump($custom, 4, 2));
        }
        return false;
    }

    /**
     * Load global SPPDocs configuration from etc/sppdocs.yml
     */
    public static function getGlobalConfig(): array
    {
        if (self::$globalConfig === null) {
            $file = __DIR__ . '/../etc/sppdocs.yml';
            if (file_exists($file)) {
                self::$globalConfig = Yaml::parseFile($file) ?: [];
            } else {
                self::$globalConfig = [];
            }
        }
        return self::$globalConfig;
    }

    /**
     * Check whether a feature is enabled globally and for a specific project.
     */
    public static function isEnabled(string $feature, ?array $project = null): bool
    {
        // 1. Check project-level override first
        if ($project && isset($project['features'][$feature])) {
            $conf = $project['features'][$feature];
            if (is_bool($conf)) return $conf;
            if (is_array($conf)) return !empty($conf['enabled']);
        }

        // Check legacy project link overrides (e.g. issues link or forum link)
        if ($feature === 'issues' && isset($project['links']['issues']) && $project['links']['issues'] === false) {
            return false;
        }
        if ($feature === 'forums' && isset($project['links']['forum']) && $project['links']['forum'] === false) {
            return false;
        }

        // 2. Check global configuration
        $global = self::getGlobalConfig();
        if (isset($global['features'][$feature])) {
            $conf = $global['features'][$feature];
            if (is_bool($conf)) return $conf;
            if (is_array($conf)) return !empty($conf['enabled']);
        }

        // 3. Fall back to defaults
        return !empty(self::$defaultFeatures[$feature]['enabled']);
    }

    /**
     * Enforce that a feature is enabled.
     * If the feature is outsourced to an external URL, this automatically redirects the user.
     * If disabled entirely, it aborts with HTTP 404.
     */
    public static function requireFeature(string $feature, ?array $project = null, ?string $projectId = null): void
    {
        if (!self::isEnabled($feature, $project)) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if ($projectId && str_contains($uri, '/admin/')) {
                if (session_status() === PHP_SESSION_NONE) {
                    @session_start();
                }
                $_SESSION['adm_flash_warning'] = "The '{$feature}' module is currently disabled for this project. You can enable it below in Feature Configuration.";
                Response::redirect(\SPP\App::getBaseUrl() . "/admin/project/" . urlencode($projectId) . "?tab=tab-features");
                exit;
            }

            http_response_code(404);
            exit("Feature '{$feature}' is disabled for this project.");
        }

        // Check if feature is outsourced and redirect
        if ($feature === 'issues' && $project && $projectId) {
            $driver = self::getIssueDriver($project, $projectId);
            if ($driver->isOutsourced()) {
                Response::redirect($driver->getWebUrl());
            }
        }

        if ($feature === 'forums' && $project && $projectId) {
            $driver = self::getForumDriver($project, $projectId);
            if ($driver->isOutsourced()) {
                Response::redirect($driver->getWebUrl());
            }
        }
    }

    /**
     * Resolve the IssueTrackerDriverInterface instance for a project
     */
    public static function getIssueDriver(array $project, string $projectId): IssueTrackerDriverInterface
    {
        $featureConf = $project['features']['issues'] ?? [];
        $driverType = is_array($featureConf) ? ($featureConf['driver'] ?? null) : null;

        // Legacy compatibility: check if links.issues is an external URL
        if (!$driverType && !empty($project['links']['issues'])) {
            $link = $project['links']['issues'];
            if (str_starts_with($link, 'http://') || str_starts_with($link, 'https://')) {
                if (str_contains($link, 'github.com')) {
                    if (preg_match('#github\.com/([^/]+)/([^/]+)#', $link, $m)) {
                        return new GitHubIssueDriver(['owner' => $m[1], 'repo' => $m[2]]);
                    }
                }
                return new ExternalLinkDriver(['url' => $link]);
            }
        }

        $driverType = $driverType ?: 'internal';

        switch ($driverType) {
            case 'github':
                $ghConf = $featureConf['github'] ?? [];
                return new GitHubIssueDriver($ghConf);

            case 'external_link':
            case 'jira':
            case 'linear':
            case 'redmine':
                return new ExternalLinkDriver($featureConf);

            case 'internal':
            default:
                $issuesDir = $project['issues_dir'] ?? (dirname($project['_config_path'] ?? '') . '/issues');
                return new InternalIssueDriver([
                    'issues_dir' => $issuesDir,
                    'project_id' => $projectId,
                ]);
        }
    }

    /**
     * Resolve the ForumDriverInterface instance for a project
     */
    public static function getForumDriver(array $project, string $projectId): ForumDriverInterface
    {
        $featureConf = $project['features']['forums'] ?? [];
        $driverType = is_array($featureConf) ? ($featureConf['driver'] ?? null) : null;

        // Legacy compatibility: check if links.forum is an external URL
        if (!$driverType && !empty($project['links']['forum'])) {
            $link = $project['links']['forum'];
            if (str_starts_with($link, 'http://') || str_starts_with($link, 'https://')) {
                return new ExternalForumDriver(['url' => $link]);
            }
        }

        $driverType = $driverType ?: 'internal';

        switch ($driverType) {
            case 'external_link':
            case 'discourse':
            case 'discord':
            case 'flarum':
                return new ExternalForumDriver($featureConf);

            case 'internal':
            default:
                $forumsDir = dirname($project['_config_path'] ?? '') . '/forums';
                return new InternalForumDriver([
                    'forums_dir' => $forumsDir,
                    'project_id' => $projectId,
                ]);
        }
    }

    /**
     * Returns an array of navigation tabs/links for a project based on enabled features.
     */
    public static function getNavigationItems(array $project, string $projectId): array
    {
        $items = [];

        // 1. Documentation
        if (self::isEnabled('docs', $project)) {
            $version = $project['default_version'] ?? 'v1';
            $items['docs'] = [
                'title' => 'Docs',
                'url' => App::url("docs/{$projectId}/{$version}/index"),
                'is_external' => false,
                'icon' => '📖'
            ];
        }

        // 2. Issue Tracker
        if (self::isEnabled('issues', $project)) {
            $driver = self::getIssueDriver($project, $projectId);
            $items['issues'] = [
                'title' => 'Issues',
                'url' => $driver->getWebUrl(),
                'is_external' => $driver->isOutsourced(),
                'icon' => '🎯'
            ];
        }

        // 3. Kanban Board (Internal only)
        if (self::isEnabled('issues', $project)) {
            $driver = self::getIssueDriver($project, $projectId);
            if (!$driver->isOutsourced()) {
                $items['board'] = [
                    'title' => 'Board',
                    'url' => App::url('issues/board') . '?projectId=' . urlencode($projectId),
                    'is_external' => false,
                    'icon' => '📋'
                ];
            }
        }

        // 4. Milestones
        if (self::isEnabled('milestones', $project)) {
            $items['milestones'] = [
                'title' => 'Milestones',
                'url' => App::url('milestones') . '?projectId=' . urlencode($projectId),
                'is_external' => false,
                'icon' => '🏁'
            ];
        }

        // 5. PM Dashboard
        if (self::isEnabled('pm_dashboard', $project)) {
            $items['pm_dashboard'] = [
                'title' => 'Dashboard',
                'url' => App::url('pm/dashboard') . '?projectId=' . urlencode($projectId),
                'is_external' => false,
                'icon' => '📊'
            ];
        }

        // 6. Forums / Community
        if (self::isEnabled('forums', $project)) {
            $driver = self::getForumDriver($project, $projectId);
            $items['forums'] = [
                'title' => 'Community',
                'url' => $driver->getWebUrl(),
                'is_external' => $driver->isOutsourced(),
                'icon' => '💬'
            ];
        }

        // 7. Academy & Courses (LMS)
        if (self::isEnabled('lms', $project)) {
            $items['lms'] = [
                'title' => 'Academy',
                'url' => App::url('lms') . '?projectId=' . urlencode($projectId),
                'is_external' => false,
                'icon' => '🎓'
            ];
        }

        return $items;
    }

    /**
     * Normalizes a URL to ensure external links have a proper scheme (https://).
     * Prevents browser from treating domains like 'github.com/...' as relative routes.
     */
    public static function normalizeUrl(?string $url): string
    {
        return \SPP\Core\Url::external($url);
    }
}
