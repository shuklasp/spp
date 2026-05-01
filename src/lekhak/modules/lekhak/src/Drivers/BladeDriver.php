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
            $blade = new \SPPMod\SPPBlade\SPPBlade();
            
            // If we have a file path, extract the view name for BladeOne
            // BladeOne expects a view name (e.g. 'node'), not a full filesystem path
            if (isset($context['path']) && file_exists($context['path'])) {
                $basename = basename($context['path']);
                // Strip .blade.php or .php extension to get view name
                $viewName = preg_replace('/\.blade\.php$|\.php$/', '', $basename);
                return $blade->render($viewName, $data);
            }
            
            // Fallback to string rendering
            $engine = $blade->getEngine();
            return $engine->runString($content, $data);
        });
    }
}
