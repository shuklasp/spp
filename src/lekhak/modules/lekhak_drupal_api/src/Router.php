<?php
namespace Lekhak\Modules\LekhakDrupalApi;

use Lekhak\Modules\LekhakDrupalApi\Controller\NodeController;
use Lekhak\Modules\LekhakDrupalApi\Controller\TermController;
use Lekhak\Modules\LekhakDrupalApi\Controller\UserController;
use Lekhak\Modules\LekhakDrupalApi\Controller\CommentController;
use Lekhak\Modules\LekhakDrupalApi\Controller\AuthController;
use Lekhak\Modules\LekhakDrupalApi\Controller\FileController;
use Lekhak\Modules\LekhakDrupalApi\Controller\GenericEntityController;

class Router {
    
    public static function handle($path, $method) {
        // Remove trailing slash
        $path = rtrim($path, '/');

        // /user/login?_format=json
        if ($method === 'POST' && preg_match('#^/user/login$#', $path) && isset($_GET['_format']) && $_GET['_format'] === 'json') {
            header('Content-Type: application/json');
            $controller = new AuthController();
            echo $controller->login();
            exit;
        }

        // Handle JSON:API standard paths
        if (strpos($path, '/jsonapi/') === 0) {
            header('Content-Type: application/vnd.api+json');
            
            // Route /jsonapi/node/{bundle}
            if (preg_match('#^/jsonapi/node/([^/]+)$#', $path, $matches)) {
                $bundle = $matches[1];
                $controller = new NodeController();
                if ($method === 'POST') {
                    echo $controller->createNode($bundle);
                } else {
                    echo $controller->getNodes($bundle);
                }
                exit;
            }
            
            // Route /jsonapi/node/{bundle}/{uuid}
            if (preg_match('#^/jsonapi/node/([^/]+)/([^/]+)$#', $path, $matches)) {
                $bundle = $matches[1];
                $uuid = $matches[2];
                $controller = new NodeController();
                if ($method === 'PATCH') {
                    echo $controller->updateNode($bundle, $uuid);
                } elseif ($method === 'DELETE') {
                    echo $controller->deleteNode($bundle, $uuid);
                } else {
                    echo $controller->getNodeByUuid($bundle, $uuid);
                }
                exit;
            }

            // Route /jsonapi/taxonomy_term/{vocabulary}
            if (preg_match('#^/jsonapi/taxonomy_term/([^/]+)$#', $path, $matches)) {
                $vocabulary = $matches[1];
                $controller = new TermController();
                echo $controller->getTerms($vocabulary);
                exit;
            }

            // Route /jsonapi/user/user
            if (preg_match('#^/jsonapi/user/user$#', $path)) {
                $controller = new UserController();
                echo $controller->getUsers();
                exit;
            }

            // Route /jsonapi/comment/{comment_type}
            if (preg_match('#^/jsonapi/comment/([^/]+)$#', $path, $matches)) {
                $type = $matches[1];
                $controller = new CommentController();
                echo $controller->getComments($type);
                exit;
            }

            // Route /jsonapi/file/file
            if (preg_match('#^/jsonapi/file/file$#', $path)) {
                $controller = new FileController();
                if ($method === 'POST') {
                    echo $controller->createFile();
                } else {
                    echo $controller->getFiles();
                }
                exit;
            }
            if (preg_match('#^/jsonapi/file/file/([^/]+)$#', $path, $matches)) {
                $uuid = $matches[1];
                $controller = new FileController();
                if ($method === 'DELETE') {
                    echo $controller->deleteFile($uuid);
                } else {
                    echo $controller->getFile($uuid);
                }
                exit;
            }

            // Fallback for jsonapi root
            if ($path === '/jsonapi') {
                echo json_encode([
                    "jsonapi" => ["version" => "1.0"],
                    "meta" => ["links" => ["self" => ["href" => "http://jsonapi.org/format/1.0/"]]],
                    "data" => []
                ]);
                exit;
            }

            // Fallback for ANY OTHER entity type / bundle
            if (preg_match('#^/jsonapi/([^/]+)/([^/]+)$#', $path, $matches)) {
                $entityType = $matches[1];
                $bundle = $matches[2];
                $controller = new GenericEntityController();
                if ($method === 'POST') {
                    echo $controller->createEntity($entityType, $bundle);
                } else {
                    echo $controller->getEntities($entityType, $bundle);
                }
                exit;
            }
            
            if (preg_match('#^/jsonapi/([^/]+)/([^/]+)/([^/]+)$#', $path, $matches)) {
                $entityType = $matches[1];
                $bundle = $matches[2];
                $uuid = $matches[3];
                $controller = new GenericEntityController();
                if ($method === 'PATCH') {
                    echo $controller->updateEntity($entityType, $bundle, $uuid);
                } elseif ($method === 'DELETE') {
                    echo $controller->deleteEntity($entityType, $bundle, $uuid);
                } else {
                    echo $controller->getEntity($entityType, $bundle, $uuid);
                }
                exit;
            }
        }

        // Basic REST format fallback
        if (strpos($path, '/node/') === 0 && isset($_GET['_format']) && $_GET['_format'] === 'json') {
            header('Content-Type: application/json');
            if (preg_match('#^/node/(\d+)$#', $path, $matches)) {
                $id = $matches[1];
                $controller = new NodeController();
                echo $controller->getRestNode($id);
                exit;
            }
        }
    }
}
