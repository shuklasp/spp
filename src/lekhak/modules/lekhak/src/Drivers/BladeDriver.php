<?php
namespace SPPMod\Lekhak\Drivers;

use SPPMod\Lekhak\Core\Renderer;

/**
 * Class BladeDriver
 * Integrates SPPBlade into the Lekhak Pipeline.
 */
class BladeDriver
{
    public static function register(Renderer $renderer): void
    {
        $renderer->registerDriver('blade', function($content, $data, $context) {
            $blade = new \SPPMod\Drishyam\SPPBlade();
            
            // If we have a file path, extract the view name for BladeOne
            // BladeOne expects a view name (e.g. 'node'), not a full filesystem path
            if (isset($context['path']) && file_exists($context['path'])) {
                $app = \SPP\App::getApp();
                $viewsDir = realpath($app->getAppSrcDir() . '/resources/views');
                $templatePath = realpath($context['path']);
                
                if ($viewsDir && str_starts_with($templatePath, $viewsDir)) {
                    $viewName = substr($templatePath, strlen($viewsDir));
                    $viewName = ltrim($viewName, '/\\');
                    $viewName = preg_replace('/\.blade\.php$|\.php$/', '', $viewName);
                    // Replace DS with dot for Blade
                    $viewName = str_replace(['/', '\\'], '.', $viewName);
                    return $blade->render($viewName, $data);
                }
                
                $basename = basename($context['path']);
                $viewName = preg_replace('/\.blade\.php$|\.php$/', '', $basename);
                return $blade->render($viewName, $data);
            }
            
            // Fallback to string rendering
            $engine = $blade->getEngine();
            return $engine->runString($content, $data);
        });
    }
}
