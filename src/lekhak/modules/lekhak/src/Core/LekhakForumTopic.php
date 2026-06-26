<?php
namespace SPPMod\Lekhak\Core;

use SPPMod\SPPDB\SPPEntity;

/**
 * Class LekhakForumTopic
 * Represents a forum discussion topic linked to a specific taxonomy container.
 */
class LekhakForumTopic extends SPPEntity
{
    protected ?string $storage_strategy = 'dynamic';
    protected string $table = 'lekhak_forum_topics';
    protected ?string $sequence = 'lekhak_forum_topics_seq';

    public function before_save()
    {
        parent::before_save();
        $now = date('Y-m-d H:i:s');
        $this->changed = $now;
        if (!$this->id) {
            $this->created = $now;
            if (!$this->author_id && class_exists('\SPPMod\SPPAuth\SPPAuth')) {
                $user = \SPPMod\SPPAuth\SPPAuth::user();
                $userId = $user->id ?? (method_exists($user, 'getId') ? $user->getId() : null);
                if (is_numeric($userId)) {
                    $this->author_id = (int) $userId;
                }
            }
            if (!$this->status) {
                $this->status = 'open';
            }
        }

        if (function_exists('lekhak_invoke_alter')) {
            lekhak_invoke_alter('entity_presave', $this);
        }
    }

    public function after_save()
    {
        parent::after_save();
        if (function_exists('lekhak_invoke_all')) {
            lekhak_invoke_all('entity_insert', [$this]);
        }
    }

    /**
     * Check if a given user has access to a specific operation on this forum topic.
     */
    public function checkAccess(string $op, $user = null): bool
    {
        if ($user === null && class_exists('\SPPMod\SPPAuth\SPPAuth')) {
            $user = \SPPMod\SPPAuth\SPPAuth::user();
        }

        // Admin user has all access
        if ($user && isset($user->roles) && in_array('administrator', $user->roles)) {
            return true;
        }

        if ($op === 'view') {
            return $this->status !== 'hidden';
        }

        if (($op === 'update' || $op === 'delete') && $user) {
            $userId = $user->id ?? (method_exists($user, 'getId') ? $user->getId() : null);
            // Forum moderators can do anything
            if (isset($user->roles) && in_array('moderator', $user->roles)) {
                return true;
            }

            // Authors can edit/delete their own forum topics
            if (is_numeric($userId) && $this->author_id == $userId) {
                return true;
            }
        }

        return false;
    }

    public function define_attributes()
    {
        return [
            'forum_id' => 'bigint',
            'title' => 'varchar(255)',
            'body' => 'longtext',
            'author_id' => 'bigint',
            'status' => 'varchar(20)',
            'created' => 'datetime',
            'changed' => 'datetime'
        ];
    }

    public function field_metadata()
    {
        return [
            'forum_id' => [
                'label' => 'Forum Category (Term ID)',
                'type' => 'number',
                'help' => 'The ID of the Taxonomy Term serving as the forum category.'
            ],
            'title' => [
                'label' => 'Topic Title',
                'type' => 'text',
                'help' => 'The headline for this forum discussion.'
            ],
            'body' => [
                'label' => 'Discussion Body',
                'type' => 'textarea',
                'help' => 'The initial post starting the discussion.'
            ],
            'status' => [
                'label' => 'Status',
                'type' => 'select',
                'options' => [
                    'open' => 'Open for replies',
                    'closed' => 'Locked/Closed',
                    'hidden' => 'Hidden'
                ]
            ]
        ];
    }
}
