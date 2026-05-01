<?php
namespace SPPMod\Lekhak\Drivers;

use SPPMod\Lekhak\Core\Renderer;

/**
 * Class TwigShimDriver
 * A zero-dependency, regex-based Twig parser for Drupal compatibility.
 */
class TwigShimDriver
{
    public static function register(Renderer $renderer): void
    {
        $renderer->registerDriver('twig', function($content, $data) {
            $parser = new self();
            return $parser->parse($content, $data);
        });
    }

    public function parse(string $content, array $data): string
    {
        // 1. Resolve Variables {{ var.property }}
        $content = preg_replace_callback('/{{\s*([a-zA-Z0-9_\.]+)\s*}}/', function($m) use ($data) {
            return $this->resolvePath($m[1], $data);
        }, $content);

        // 2. Resolve If Statements {% if var %} ... {% endif %}
        $content = preg_replace_callback('/{%\s*if\s+([a-zA-Z0-9_\.]+)\s*%}(.*?){%\s*endif\s*%}/s', function($m) use ($data) {
            $val = $this->resolvePath($m[1], $data);
            return $val ? $m[2] : '';
        }, $content);

        // 3. Resolve For Loops {% for item in items %} ... {% endfor %}
        $content = preg_replace_callback('/{%\s*for\s+([a-zA-Z0-9_]+)\s+in\s+([a-zA-Z0-9_\.]+)\s*%}(.*?){%\s*endfor\s*%}/s', function($m) use ($data) {
            $items = $this->resolvePath($m[2], $data);
            if (!is_iterable($items)) return '';
            
            $out = '';
            foreach ($items as $item) {
                $itemData = array_merge($data, [$m[1] => $item]);
                $out .= $this->parse($m[3], $itemData);
            }
            return $out;
        }, $content);

        // 4. Resolve Drupal Filters |t, |without, etc.
        $content = preg_replace('/\|\s*t/', '', $content); // Simple strip for now

        return $content;
    }

    protected function resolvePath(string $path, array $data)
    {
        $parts = explode('.', $path);
        $val = $data;
        foreach ($parts as $p) {
            if (is_array($val) && isset($val[$p])) {
                $val = $val[$p];
            } elseif (is_object($val)) {
                if (isset($val->$p)) {
                    $val = $val->$p;
                } elseif (method_exists($val, 'get' . ucfirst($p))) {
                    $val = $val->{'get' . ucfirst($p)}();
                } elseif (method_exists($val, 'get')) {
                    $val = $val->get($p);
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }
        return $val;
    }
}
