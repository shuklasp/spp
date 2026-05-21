<?php
namespace App\Lekhak\Workflow;

use SPP\Core\WorkflowManager;

/**
 * ContentWorkflow
 *
 * Registers Lekhak's content-specific editorial workflow with the
 * framework's WorkflowManager. This defines the states and transitions
 * that content nodes pass through during their lifecycle.
 *
 * States:  draft → in_review → published → archived
 * Also supports: draft → published (fast-publish) with permission.
 */
class ContentWorkflow
{
    /**
     * Register the content workflow definitions with the core WorkflowManager.
     * Called during Lekhak's application boot.
     */
    public static function register(): void
    {
        // ── Node workflow (applies to all content types by default) ────
        WorkflowManager::registerWorkflow('node', [
            'label'  => 'Content Moderation',
            'states' => [
                'draft'      => ['label' => 'Draft',      'color' => '#6c757d'],
                'in_review'  => ['label' => 'In Review',  'color' => '#ffc107'],
                'published'  => ['label' => 'Published',  'color' => '#28a745'],
                'archived'   => ['label' => 'Archived',   'color' => '#17a2b8'],
                'needs_translation' => ['label' => 'Needs Translation', 'color' => '#fd7e14'],
            ],
            'initial_state' => 'draft',
            'transitions' => [
                'submit_for_review' => [
                    'label' => 'Submit for Review',
                    'from'  => ['draft', 'needs_translation'],
                    'to'    => 'in_review',
                    'permission' => 'content.submit_review',
                ],
                'approve' => [
                    'label' => 'Approve & Publish',
                    'from'  => ['in_review'],
                    'to'    => 'published',
                    'permission' => 'content.approve',
                ],
                'reject' => [
                    'label' => 'Reject (Send Back)',
                    'from'  => ['in_review'],
                    'to'    => 'draft',
                    'permission' => 'content.reject',
                ],
                'mark_for_translation' => [
                    'label' => 'Mark for Translation',
                    'from'  => ['published', 'draft'],
                    'to'    => 'needs_translation',
                    'permission' => 'content.translate',
                ],
                'translation_complete' => [
                    'label' => 'Translation Complete',
                    'from'  => ['needs_translation'],
                    'to'    => 'draft',
                    'permission' => 'content.translate',
                ],
                'fast_publish' => [
                    'label' => 'Publish Directly',
                    'from'  => ['draft', 'needs_translation'],
                    'to'    => 'published',
                    'permission' => 'content.fast_publish',
                ],
                'unpublish' => [
                    'label' => 'Unpublish',
                    'from'  => ['published'],
                    'to'    => 'draft',
                    'permission' => 'content.unpublish',
                ],
                'archive' => [
                    'label' => 'Archive',
                    'from'  => ['published', 'draft', 'needs_translation'],
                    'to'    => 'archived',
                    'permission' => 'content.archive',
                ],
                'restore' => [
                    'label' => 'Restore from Archive',
                    'from'  => ['archived'],
                    'to'    => 'draft',
                    'permission' => 'content.restore',
                ],
            ],
        ]);

        // ── Article-specific workflow (stricter — requires double review) ──
        WorkflowManager::registerWorkflow('node', [
            'label'  => 'Article Moderation',
            'states' => [
                'draft'           => ['label' => 'Draft',             'color' => '#6c757d'],
                'in_review'       => ['label' => 'Editorial Review',  'color' => '#ffc107'],
                'editor_approved' => ['label' => 'Editor Approved',   'color' => '#20c997'],
                'published'       => ['label' => 'Published',         'color' => '#28a745'],
                'archived'        => ['label' => 'Archived',          'color' => '#17a2b8'],
            ],
            'initial_state' => 'draft',
            'transitions' => [
                'submit_for_review' => [
                    'label' => 'Submit for Review',
                    'from'  => ['draft'],
                    'to'    => 'in_review',
                    'permission' => 'content.submit_review',
                ],
                'editor_approve' => [
                    'label' => 'Editor Approval',
                    'from'  => ['in_review'],
                    'to'    => 'editor_approved',
                    'permission' => 'content.editor_approve',
                ],
                'final_publish' => [
                    'label' => 'Final Publish',
                    'from'  => ['editor_approved'],
                    'to'    => 'published',
                    'permission' => 'content.approve',
                ],
                'reject' => [
                    'label' => 'Reject',
                    'from'  => ['in_review', 'editor_approved'],
                    'to'    => 'draft',
                    'permission' => 'content.reject',
                ],
                'archive' => [
                    'label' => 'Archive',
                    'from'  => ['published'],
                    'to'    => 'archived',
                    'permission' => 'content.archive',
                ],
            ],
        ], 'article');
    }
}
