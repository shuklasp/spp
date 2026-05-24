<?php
namespace Lekhak\Modules\LekhakDrupalApi\Serializer;

class JsonApiSerializer {
    
    public static function serializeNode($node, $type) {
        $nodeType = $node['bundle'] ?? $type;
        $uuid = self::generateFakeUuid('node_' . $node['id']);
        $baseUrl = rtrim(\SPP\App::getBaseUrl(), '/');

        return [
            'type' => "node--{$nodeType}",
            'id' => $uuid,
            'attributes' => [
                'drupal_internal__nid' => (int)$node['id'],
                'drupal_internal__vid' => (int)$node['id'],
                'title' => $node['title'] ?? '',
                'created' => gmdate('Y-m-d\TH:i:sP', strtotime($node['created'] ?? 'now')),
                'changed' => gmdate('Y-m-d\TH:i:sP', strtotime($node['changed'] ?? 'now')),
                'promote' => true,
                'sticky' => false,
                'default_langcode' => true,
                'revision_translation_affected' => true,
                'path' => [
                    'alias' => $node['alias'] ?? "/node/{$node['id']}",
                    'pid' => null,
                    'langcode' => 'en',
                ],
                'body' => [
                    'value' => $node['body'] ?? '',
                    'format' => 'full_html',
                    'processed' => $node['body'] ?? '',
                    'summary' => strip_tags(substr($node['body'] ?? '', 0, 200))
                ],
            ],
            'links' => [
                'self' => [
                    'href' => $baseUrl . "/jsonapi/node/{$nodeType}/{$uuid}"
                ]
            ]
        ];
    }

    public static function serializeTerm($term, $vocabulary) {
        $uuid = self::generateFakeUuid('term_' . $term['id']);
        $baseUrl = rtrim(\SPP\App::getBaseUrl(), '/');

        return [
            'type' => "taxonomy_term--{$vocabulary}",
            'id' => $uuid,
            'attributes' => [
                'drupal_internal__tid' => (int)$term['id'],
                'name' => $term['name'] ?? '',
                'description' => [
                    'value' => $term['description'] ?? '',
                    'format' => 'full_html',
                    'processed' => $term['description'] ?? ''
                ],
                'weight' => (int)($term['weight'] ?? 0),
                'path' => [
                    'alias' => "/taxonomy/term/{$term['id']}",
                    'pid' => null,
                    'langcode' => 'en'
                ]
            ],
            'links' => [
                'self' => [
                    'href' => $baseUrl . "/jsonapi/taxonomy_term/{$vocabulary}/{$uuid}"
                ]
            ]
        ];
    }

    public static function serializeUser($user) {
        $uuid = self::generateFakeUuid('user_' . $user['id']);
        $baseUrl = rtrim(\SPP\App::getBaseUrl(), '/');

        return [
            'type' => "user--user",
            'id' => $uuid,
            'attributes' => [
                'drupal_internal__uid' => (int)$user['id'],
                'name' => $user['username'] ?? '',
                'mail' => $user['email'] ?? '',
                'created' => gmdate('Y-m-d\TH:i:sP', strtotime($user['created_at'] ?? 'now')),
                'changed' => gmdate('Y-m-d\TH:i:sP', strtotime($user['updated_at'] ?? 'now')),
                'status' => ($user['status'] === 'active' || $user['status'] === '1' || $user['status'] === 1) ? true : false,
                'path' => [
                    'alias' => "/user/{$user['id']}",
                    'pid' => null,
                    'langcode' => 'en'
                ]
            ],
            'links' => [
                'self' => [
                    'href' => $baseUrl . "/jsonapi/user/user/{$uuid}"
                ]
            ]
        ];
    }

    public static function serializeComment($comment, $type) {
        $uuid = self::generateFakeUuid('comment_' . $comment['id']);
        $baseUrl = rtrim(\SPP\App::getBaseUrl(), '/');

        return [
            'type' => "comment--{$type}",
            'id' => $uuid,
            'attributes' => [
                'drupal_internal__cid' => (int)$comment['id'],
                'status' => (int)($comment['status'] ?? 1),
                'created' => gmdate('Y-m-d\TH:i:sP', strtotime($comment['created'] ?? 'now')),
                'changed' => gmdate('Y-m-d\TH:i:sP', strtotime($comment['changed'] ?? 'now')),
                'entity_type' => $comment['entity_type'] ?? 'node',
                'comment_body' => [
                    'value' => $comment['body'] ?? '',
                    'format' => 'plain_text',
                    'processed' => htmlspecialchars($comment['body'] ?? '')
                ]
            ],
            'relationships' => [
                'entity_id' => [
                    'data' => [
                        'type' => "node--page", // Simplified for now
                        'id' => self::generateFakeUuid('node_' . $comment['entity_id'])
                    ]
                ],
                'uid' => [
                    'data' => [
                        'type' => "user--user",
                        'id' => self::generateFakeUuid('user_' . ($comment['author_id'] ?? 0))
                    ]
                ]
            ],
            'links' => [
                'self' => [
                    'href' => $baseUrl . "/jsonapi/comment/{$type}/{$uuid}"
                ]
            ]
        ];
    }

    public static function wrapDocument($data, $isCollection = false) {
        return [
            'jsonapi' => [
                'version' => '1.0',
                'meta' => [
                    'links' => [
                        'self' => ['href' => 'http://jsonapi.org/format/1.0/']
                    ]
                ]
            ],
            'data' => $data,
            'links' => [
                'self' => [
                    'href' => $_SERVER['REQUEST_URI']
                ]
            ]
        ];
    }

    public static function serializeRestNode($node) {
        $uuid = self::generateFakeUuid('node_' . $node['id']);
        return [
            'nid' => [['value' => (int)$node['id']]],
            'uuid' => [['value' => $uuid]],
            'vid' => [['value' => (int)$node['id']]],
            'langcode' => [['value' => 'en']],
            'type' => [['target_id' => $node['bundle'] ?? 'page']],
            'title' => [['value' => $node['title'] ?? '']],
            'created' => [['value' => gmdate('Y-m-d\TH:i:sP', strtotime($node['created'] ?? 'now'))]],
            'changed' => [['value' => gmdate('Y-m-d\TH:i:sP', strtotime($node['changed'] ?? 'now'))]],
            'promote' => [['value' => true]],
            'sticky' => [['value' => false]],
            'default_langcode' => [['value' => true]],
            'path' => [['alias' => $node['alias'] ?? "/node/{$node['id']}", 'pid' => null, 'langcode' => 'en']],
            'body' => [[
                'value' => $node['body'] ?? '',
                'format' => 'full_html',
                'summary' => ''
            ]]
        ];
    }

    private static function generateFakeUuid($stringToHash) {
        $hash = md5('lekhak_entity_' . $stringToHash);
        return substr($hash, 0, 8) . '-' .
               substr($hash, 8, 4) . '-4' .
               substr($hash, 13, 3) . '-8' .
               substr($hash, 17, 3) . '-' .
               substr($hash, 20, 12);
    }
}
