<?php
namespace SPPMod\Lekhak\Core;

use SPPMod\SPPDB\SPPEntity;

/**
 * Class LekhakComment
 * Represents a threaded comment entity.
 */
class LekhakComment extends SPPEntity
{
    protected ?string $storage_strategy = 'dynamic';
    protected string $table = 'lekhak_comments';
    protected ?string $sequence = 'lekhak_comments_seq';

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
                $this->status = 'published';
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
     * Check if a given user has access to a specific operation on this comment.
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
            return $this->status === 'published';
        }

        if (($op === 'update' || $op === 'delete') && $user) {
            $userId = $user->id ?? (method_exists($user, 'getId') ? $user->getId() : null);
            // Authors can edit/delete their own comments
            if (is_numeric($userId) && $this->author_id == $userId) {
                return true;
            }
        }

        return false;
    }

    public function define_attributes()
    {
        return [
            'node_id' => 'bigint',
            'author_id' => 'bigint',
            'parent_id' => 'bigint',
            'body' => 'longtext',
            'status' => 'varchar(20)',
            'created' => 'datetime',
            'changed' => 'datetime'
        ];
    }

    public function field_metadata()
    {
        return [
            'node_id' => [
                'label' => 'Parent Node ID',
                'type' => 'number',
                'help' => 'The ID of the content this comment belongs to.'
            ],
            'parent_id' => [
                'label' => 'Parent Comment ID',
                'type' => 'number',
                'help' => 'If this is a reply, the ID of the parent comment.'
            ],
            'body' => [
                'label' => 'Comment Body',
                'type' => 'textarea',
                'help' => 'The actual text of the comment.'
            ],
            'status' => [
                'label' => 'Status',
                'type' => 'select',
                'options' => [
                    'published' => 'Published',
                    'pending_moderation' => 'Pending Moderation',
                    'spam' => 'Spam'
                ]
            ]
        ];
    }
}
