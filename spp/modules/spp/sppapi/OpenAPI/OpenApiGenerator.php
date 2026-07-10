<?php

namespace SPPMod\SPPAPI\OpenAPI;

use Symfony\Component\Yaml\Yaml;

/**
 * OpenApiGenerator
 * Generates automated OpenAPI 3.1 compliant specification schemas by inspecting
 * SPPEntity configurations and Controller public actions across the application.
 */
class OpenApiGenerator
{
    /**
     * Generate the OpenAPI 3.1 schema array.
     */
    public static function generate(string $title = 'SPP Automated API Specification', string $version = '1.0.0'): array
    {
        $schema = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => $title,
                'description' => 'Zero-touch automated OpenAPI 3.1 specification generated directly from SPPEntity configs and Controllers.',
                'version' => $version
            ],
            'servers' => [
                [
                    'url' => defined('APP_BASE_URI') ? APP_BASE_URI : '/',
                    'description' => 'Current Environment Server'
                ]
            ],
            'paths' => [],
            'components' => [
                'securitySchemes' => [
                    'BearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                        'description' => 'SPP Bearer Token Authentication'
                    ]
                ],
                'schemas' => []
            ],
            'security' => [
                ['BearerAuth' => []]
            ]
        ];

        // 1. Inspect SPPEntity configs
        $srcDir = defined('SPP_APP_DIR') ? SPP_APP_DIR . '/src' : getcwd() . '/src';
        $yamlFiles = [];

        if (is_dir($srcDir)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcDir));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'yml') {
                    if (basename(dirname($file->getPathname())) === 'entities') {
                        $yamlFiles[] = $file->getPathname();
                    }
                }
            }
        }

        foreach ($yamlFiles as $yaml) {
            $data = Yaml::parseFile($yaml);
            $base = basename($yaml, '.yml');
            $entityName = preg_replace('/^entity\./', '', $base);

            if ((isset($data['attributes']) || isset($data['table'])) && !empty($data['enable_api'])) {
                $attrs = $data['attributes'] ?? [];
                
                // Build Component Schema for Entity
                $properties = [];
                foreach ($attrs as $attrName => $attrType) {
                    $openApiType = match(strtolower((string)$attrType)) {
                        'integer', 'int' => 'integer',
                        'boolean', 'bool' => 'boolean',
                        'float', 'double' => 'number',
                        default => 'string',
                    };
                    $properties[$attrName] = ['type' => $openApiType];
                }
                
                $schema['components']['schemas'][$entityName] = [
                    'type' => 'object',
                    'properties' => $properties
                ];

                $basePath = '/api/v1/' . $entityName;
                $itemPath = $basePath . '/{id}';

                // GET Collection
                $schema['paths'][$basePath]['get'] = [
                    'tags' => [$entityName],
                    'summary' => "List {$entityName} records",
                    'description' => "Retrieve a paginated collection of {$entityName}.",
                    'responses' => [
                        '200' => [
                            'description' => 'Successful response',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'array',
                                        'items' => ['$ref' => "#/components/schemas/{$entityName}"]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ];

                // POST Create
                $schema['paths'][$basePath]['post'] = [
                    'tags' => [$entityName],
                    'summary' => "Create a new {$entityName}",
                    'description' => "Creates a new {$entityName} record with the provided payload.",
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => "#/components/schemas/{$entityName}"]
                            ]
                        ]
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'Resource created successfully',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => "#/components/schemas/{$entityName}"]
                                ]
                            ]
                        ]
                    ]
                ];

                // GET Item
                $schema['paths'][$itemPath]['get'] = [
                    'tags' => [$entityName],
                    'summary' => "Retrieve a {$entityName}",
                    'description' => "Fetch a single {$entityName} by its primary ID.",
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'string']
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Successful response',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => "#/components/schemas/{$entityName}"]
                                ]
                            ]
                        ],
                        '404' => ['description' => 'Resource not found']
                    ]
                ];

                // PUT Update
                $schema['paths'][$itemPath]['put'] = [
                    'tags' => [$entityName],
                    'summary' => "Update a {$entityName}",
                    'description' => "Completely update a {$entityName} by its primary ID.",
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'string']
                        ]
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => "#/components/schemas/{$entityName}"]
                            ]
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Resource updated successfully',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => "#/components/schemas/{$entityName}"]
                                ]
                            ]
                        ],
                        '404' => ['description' => 'Resource not found']
                    ]
                ];

                // DELETE Item
                $schema['paths'][$itemPath]['delete'] = [
                    'tags' => [$entityName],
                    'summary' => "Delete a {$entityName}",
                    'description' => "Remove a {$entityName} by its primary ID.",
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'string']
                        ]
                    ],
                    'responses' => [
                        '204' => ['description' => 'Resource deleted successfully'],
                        '404' => ['description' => 'Resource not found']
                    ]
                ];
            }
        }

        // 2. Discover standard Controllers using Reflection
        $ctrlDir = defined('SPP_APP_DIR') ? SPP_APP_DIR . '/src/controllers' : getcwd() . '/src/controllers';
        if (is_dir($ctrlDir)) {
            $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($ctrlDir));
            foreach ($iter as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $class = '\\App\\Default\\Controllers\\' . str_replace('.php', '', $file->getFilename());
                    if (class_exists($class)) {
                        $ref = new \ReflectionClass($class);
                        $shortName = str_replace('Controller', '', $ref->getShortName());
                        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                            if ($method->class === $class && !$method->isConstructor() && !$method->isStatic()) {
                                $methodName = $method->getName();
                                $epPath = '/api/' . strtolower($shortName) . '/' . $methodName;
                                $schema['paths'][$epPath]['post'] = [
                                    'tags' => [$shortName],
                                    'summary' => "Execute {$methodName} on {$shortName}",
                                    'description' => "Controller action endpoint for {$class}::{$methodName}.",
                                    'responses' => [
                                        '200' => [
                                            'description' => 'Successful operation',
                                            'content' => [
                                                'application/json' => [
                                                    'schema' => ['type' => 'object']
                                                ]
                                            ]
                                        ]
                                    ]
                                ];
                            }
                        }
                    }
                }
            }
        }

        // 3. Add Framework Endpoints
        $tokenPath = '/api/v1/auth/token';
        $schema['paths'][$tokenPath]['post'] = [
            'tags' => ['Auth'],
            'summary' => 'Request temporary JWT Token',
            'description' => 'Exchange credentials for a Bearer JWT Token to authenticate subsequent API requests.',
            'security' => [], // Public endpoint
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'username' => ['type' => 'string'],
                                'password' => ['type' => 'string']
                            ],
                            'required' => ['username', 'password']
                        ]
                    ]
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Token issued successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'token' => ['type' => 'string'],
                                    'expires_in' => ['type' => 'integer']
                                ]
                            ]
                        ]
                    ]
                ],
                '401' => ['description' => 'Unauthorized / Invalid credentials']
            ]
        ];

        return $schema;
    }

    /**
     * Generate JSON representation of OpenAPI 3.1 schema.
     */
    public static function generateJson(string $title = 'SPP Automated API Specification', string $version = '1.0.0'): string
    {
        return json_encode(self::generate($title, $version), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
