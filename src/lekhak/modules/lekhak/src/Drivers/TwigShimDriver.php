<?php
namespace SPPMod\Lekhak\Drivers;

use SPPMod\Lekhak\Core\Renderer;

/**
 * Class DrupalAttribute
 * Provides a string-convertible object replicating Drupal's Attribute class.
 */
class DrupalAttribute
{
    protected array $classes = [];
    protected array $attrs = [];

    public function addClass($classes): self
    {
        if (is_string($classes)) {
            $this->classes = array_merge($this->classes, explode(' ', $classes));
        } elseif (is_array($classes)) {
            $this->classes = array_merge($this->classes, $classes);
        }
        return $this;
    }

    public function setAttribute(string $name, $value): self
    {
        $this->attrs[$name] = $value;
        return $this;
    }

    public function removeAttribute(string $name): self
    {
        unset($this->attrs[$name]);
        return $this;
    }

    public function __toString(): string
    {
        $str = '';
        $uniqueClasses = array_filter(array_unique(array_map('trim', $this->classes)));
        if (!empty($uniqueClasses)) {
            $str .= ' class="' . htmlspecialchars(implode(' ', $uniqueClasses)) . '"';
        }
        foreach ($this->attrs as $k => $v) {
            $str .= ' ' . htmlspecialchars($k) . '="' . htmlspecialchars((string)$v) . '"';
        }
        return $str;
    }
}

/**
 * Class TwigShimDriver
 * A zero-dependency, highly robust Twig parser for Drupal template compatibility.
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

    /**
     * Parses the Twig template content.
     */
    public function parse(string $content, array $data, ?callable $includeCallback = null): string
    {
        // Automatically inject standard Drupal variables if not present
        if (!isset($data['attributes'])) {
            $data['attributes'] = new DrupalAttribute();
        }
        if (!isset($data['title_attributes'])) {
            $data['title_attributes'] = new DrupalAttribute();
        }
        if (!isset($data['content_attributes'])) {
            $data['content_attributes'] = new DrupalAttribute();
        }

        // 1. Strip Twig Comments {# ... #}
        $content = preg_replace('/\{#.*?#\}/s', '', $content);

        // 2. Resolve Inclusions {% include '...' %}
        $content = preg_replace_callback('/\{%\s*include\s*[\'"]@?([^\'"]+)[\'"]\s*%\}/', function($m) use ($data, $includeCallback) {
            if ($includeCallback) {
                return $includeCallback($m[1], $data);
            }
            return "<!-- Include callback missing for: " . htmlspecialchars($m[1]) . " -->";
        }, $content);

        // 3. Resolve Set Arrays & Variables {% set classes = [ ... ] %}
        $content = preg_replace_callback('/\{%\s*set\s+([a-zA-Z0-9_]+)\s*=\s*(.*?)\s*%\}/s', function($m) use (&$data) {
            $varName = $m[1];
            $expr = trim($m[2]);
            $data[$varName] = $this->evaluateExpression($expr, $data);
            return '';
        }, $content);

        // 4. Resolve Non-Greedy Innermost For Loops {% for item in items %} ... {% endfor %}
        $evalFor = function($text) use (&$evalFor, &$data, $includeCallback) {
            $pattern = '/\{%\s*for\s+([a-zA-Z0-9_]+)\s+in\s+(.*?)\s*%\}((?:(?!\{%\s*for\s+).)*?)\{%\s*endfor\s*%\}/is';
            if (preg_match($pattern, $text)) {
                $processed = preg_replace_callback($pattern, function($m) use (&$data, $includeCallback) {
                    $itemName = $m[1];
                    $itemsExpr = trim($m[2]);
                    $inner = $m[3];
                    
                    $items = $this->evaluateExpression($itemsExpr, $data);
                    if (!is_iterable($items)) return '';
                    
                    $out = '';
                    foreach ($items as $item) {
                        $itemData = array_merge($data, [$itemName => $item]);
                        $out .= $this->parse($inner, $itemData, $includeCallback);
                    }
                    return $out;
                }, $text);
                return $evalFor($processed);
            }
            return $text;
        };
        $content = $evalFor($content);

        // 5. Resolve Non-Greedy Innermost If/Else Conditions {% if condition %} ... {% endif %}
        $evalIf = function($text) use (&$evalIf, &$data, $includeCallback) {
            $pattern = '/\{%\s*if\s+(.*?)\s*%\}((?:(?!\{%\s*if\s+).)*?)\{%\s*endif\s*%\}/is';
            if (preg_match($pattern, $text)) {
                $processed = preg_replace_callback($pattern, function($m) use (&$data, $includeCallback) {
                    $condExpr = trim($m[1]);
                    $inner = $m[2];
                    
                    $isTruthy = false;
                    if (str_contains($condExpr, ' or ')) {
                        foreach (explode(' or ', $condExpr) as $tok) {
                            if ($this->evaluateExpression(trim($tok), $data)) { $isTruthy = true; break; }
                        }
                    } elseif (str_contains($condExpr, ' and ')) {
                        $isTruthy = true;
                        foreach (explode(' and ', $condExpr) as $tok) {
                            if (!$this->evaluateExpression(trim($tok), $data)) { $isTruthy = false; break; }
                        }
                    } else {
                        $isTruthy = (bool)$this->evaluateExpression($condExpr, $data);
                    }
                    
                    if (str_contains($inner, '{% else %}')) {
                        $parts = explode('{% else %}', $inner, 2);
                        $body = $isTruthy ? $parts[0] : $parts[1];
                    } else {
                        $body = $isTruthy ? $inner : '';
                    }
                    return $this->parse($body, $data, $includeCallback);
                }, $text);
                return $evalIf($processed);
            }
            return $text;
        };
        $content = $evalIf($content);

        // 6. Resolve Output {{ var.property }}
        $content = preg_replace_callback('/\{\{\s*(.*?)\s*\}\}/s', function($m) use (&$data) {
            $expr = trim($m[1]);
            $val = $this->evaluateExpression($expr, $data);
            return is_scalar($val) || (is_object($val) && method_exists($val, '__toString')) ? (string)$val : '';
        }, $content);

        return $content;
    }

    /**
     * Evaluates complex expressions.
     */
    protected function evaluateExpression(string $expr, array $data)
    {
        $expr = trim($expr);
        if ($expr === '') return null;

        // Parentheses grouping check or Not Operator
        if (str_starts_with($expr, 'not ')) {
            return !$this->evaluateExpression(substr($expr, 4), $data);
        }

        // Bracketed array declaration: [ 'node', 'node-type-' ~ node.bundle ]
        if (str_starts_with($expr, '[') && str_ends_with($expr, ']')) {
            $inner = trim(substr($expr, 1, -1));
            if ($inner === '') return [];
            
            $elements = [];
            $current = '';
            $depth = 0;
            $inString = false;
            $strChar = '';
            
            for ($i = 0; $i < strlen($inner); $i++) {
                $char = $inner[$i];
                if ($inString) {
                    if ($char === $strChar && ($i === 0 || $inner[$i - 1] !== '\\')) {
                        $inString = false;
                    }
                    $current .= $char;
                } else {
                    if ($char === "'" || $char === '"') {
                        $inString = true;
                        $strChar = $char;
                        $current .= $char;
                    } elseif (in_array($char, ['[', '{', '('])) {
                        $depth++;
                        $current .= $char;
                    } elseif (in_array($char, [']', '}', ')'])) {
                        $depth--;
                        $current .= $char;
                    } elseif ($char === ',' && $depth === 0) {
                        $elements[] = trim($current);
                        $current = '';
                    } else {
                        $current .= $char;
                    }
                }
            }
            if (trim($current) !== '') {
                $elements[] = trim($current);
            }
            
            $result = [];
            foreach ($elements as $el) {
                $elVal = $this->evaluateExpression($el, $data);
                if ($elVal !== null && $elVal !== '') {
                    $result[] = $elVal;
                }
            }
            return $result;
        }

        // Depth-safe split for ternary operator: cond ? trueVal : falseVal
        $depth = 0;
        $inString = false;
        $strChar = '';
        $qPos = -1;
        $colonPos = -1;
        
        for ($i = 0; $i < strlen($expr); $i++) {
            $char = $expr[$i];
            if ($inString) {
                if ($char === $strChar && ($i === 0 || $expr[$i - 1] !== '\\')) $inString = false;
            } else {
                if ($char === "'" || $char === '"') {
                    $inString = true;
                    $strChar = $char;
                } elseif (in_array($char, ['[', '{', '('])) {
                    $depth++;
                } elseif (in_array($char, [']', '}', ')'])) {
                    $depth--;
                } elseif ($char === '?' && $depth === 0 && $qPos === -1) {
                    $qPos = $i;
                } elseif ($char === ':' && $depth === 0 && $qPos !== -1 && $colonPos === -1) {
                    $colonPos = $i;
                }
            }
        }
        if ($qPos !== -1 && $colonPos !== -1) {
            $cond = trim(substr($expr, 0, $qPos));
            $trueValExpr = trim(substr($expr, $qPos + 1, $colonPos - $qPos - 1));
            $falseValExpr = trim(substr($expr, $colonPos + 1));
            
            $condVal = $this->evaluateExpression($cond, $data);
            return !empty($condVal) ? $this->evaluateExpression($trueValExpr, $data) : $this->evaluateExpression($falseValExpr, $data);
        }

        // Depth-safe split for string concatenation: parts ~ other
        $parts = [];
        $current = '';
        $depth = 0;
        $inString = false;
        $strChar = '';
        $hasConcat = false;
        
        for ($i = 0; $i < strlen($expr); $i++) {
            $char = $expr[$i];
            if ($inString) {
                if ($char === $strChar && ($i === 0 || $expr[$i - 1] !== '\\')) $inString = false;
                $current .= $char;
            } else {
                if ($char === "'" || $char === '"') {
                    $inString = true;
                    $strChar = $char;
                    $current .= $char;
                } elseif (in_array($char, ['[', '{', '('])) {
                    $depth++;
                    $current .= $char;
                } elseif (in_array($char, [']', '}', ')'])) {
                    $depth--;
                    $current .= $char;
                } elseif ($char === '~' && $depth === 0) {
                    $parts[] = trim($current);
                    $current = '';
                    $hasConcat = true;
                } else {
                    $current .= $char;
                }
            }
        }
        if ($hasConcat) {
            if (trim($current) !== '') $parts[] = trim($current);
            $out = '';
            foreach ($parts as $p) {
                $out .= (string)$this->evaluateExpression($p, $data);
            }
            return $out;
        }

        // String literals check
        if ((str_starts_with($expr, "'") && str_ends_with($expr, "'")) || 
            (str_starts_with($expr, '"') && str_ends_with($expr, '"'))) {
            return substr($expr, 1, -1);
        }
        if (is_numeric($expr)) {
            return str_contains($expr, '.') ? (float)$expr : (int)$expr;
        }
        if ($expr === 'true') return true;
        if ($expr === 'false') return false;
        if ($expr === 'null') return null;

        // Depth-safe filters check: baseExpr | filterExpr
        $filterParts = [];
        $current = '';
        $depth = 0;
        $inString = false;
        $strChar = '';
        $hasFilter = false;
        
        for ($i = 0; $i < strlen($expr); $i++) {
            $char = $expr[$i];
            if ($inString) {
                if ($char === $strChar && ($i === 0 || $expr[$i - 1] !== '\\')) $inString = false;
                $current .= $char;
            } else {
                if ($char === "'" || $char === '"') {
                    $inString = true;
                    $strChar = $char;
                    $current .= $char;
                } elseif (in_array($char, ['[', '{', '('])) {
                    $depth++;
                    $current .= $char;
                } elseif (in_array($char, [']', '}', ')'])) {
                    $depth--;
                    $current .= $char;
                } elseif ($char === '|' && $depth === 0) {
                    $filterParts[] = trim($current);
                    $current = '';
                    $hasFilter = true;
                } else {
                    $current .= $char;
                }
            }
        }
        if ($hasFilter) {
            if (trim($current) !== '') $filterParts[] = trim($current);
            $baseExpr = array_shift($filterParts);
            $val = $this->evaluateExpression($baseExpr, $data);
            foreach ($filterParts as $filterExpr) {
                $val = $this->applyFilter($filterExpr, $val, $data);
            }
            return $val;
        }

        // Otherwise resolve variable path
        return $this->resolvePath($expr, $data);
    }

    /**
     * Applies standard Twig filters.
     */
    protected function applyFilter(string $filterExpr, $val, array $data)
    {
        $filterExpr = trim($filterExpr);
        $args = [];
        $filterName = $filterExpr;
        
        if (preg_match('/^([a-zA-Z0-9_]+)\((.*)\)$/', $filterExpr, $m)) {
            $filterName = $m[1];
            $argsStr = trim($m[2]);
            if ($argsStr !== '') {
                foreach (explode(',', $argsStr) as $arg) {
                    $args[] = $this->evaluateExpression(trim($arg), $data);
                }
            }
        }
        
        switch ($filterName) {
            case 'clean_class':
                $s = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', (string)$val));
                return trim($s, '-');
            case 't':
                return $val;
            case 'date':
                $format = !empty($args) ? $args[0] : 'Y-m-d';
                $timestamp = is_numeric($val) ? (int)$val : strtotime((string)$val);
                return $timestamp ? date($format, $timestamp) : '';
            case 'without':
                if (is_array($val)) {
                    foreach ($args as $arg) {
                        unset($val[$arg]);
                    }
                }
                return $val;
            default:
                return $val;
        }
    }

    /**
     * Dynamically resolves paths including object properties, methods, and compatibility overrides.
     */
    protected function resolvePath(string $path, array $data)
    {
        $path = trim($path);
        if ($path === '') return null;

        // Split segments respecting method parentheses
        $segments = [];
        $current = '';
        $depth = 0;
        for ($i = 0; $i < strlen($path); $i++) {
            $char = $path[$i];
            if ($char === '(') $depth++;
            if ($char === ')') $depth--;
            
            if ($char === '.' && $depth === 0) {
                $segments[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }
        if (trim($current) !== '') {
            $segments[] = trim($current);
        }

        $val = $data;
        foreach ($segments as $seg) {
            $seg = trim($seg);
            if ($seg === '') continue;

            $methodArgs = [];
            $isMethod = false;
            if (preg_match('/^([a-zA-Z0-9_]+)\((.*)\)$/', $seg, $m)) {
                $isMethod = true;
                $methodName = $m[1];
                $argsStr = trim($m[2]);
                if ($argsStr !== '') {
                    foreach (explode(',', $argsStr) as $arg) {
                        $methodArgs[] = $this->evaluateExpression(trim($arg), $data);
                    }
                }
            } else {
                $methodName = $seg;
            }

            if (is_array($val)) {
                if ($isMethod) return null;
                if (isset($val[$methodName])) {
                    $val = $val[$methodName];
                } else {
                    return null;
                }
            } elseif (is_object($val)) {
                if ($isMethod) {
                    if (method_exists($val, $methodName)) {
                        $val = call_user_func_array([$val, $methodName], $methodArgs);
                    } elseif ($val instanceof \SPPMod\Lekhak\Core\LekhakNode) {
                        // Compatibility features for LekhakNode
                        if ($methodName === 'isPublished') {
                            $val = ($val->status === 'published');
                        } elseif ($methodName === 'isPromoted') {
                            $val = !empty($val->promoted);
                        } elseif ($methodName === 'isSticky') {
                            $val = false;
                        } elseif ($methodName === 'getCreatedTime') {
                            $val = strtotime($val->created ?? 'now');
                        } elseif ($methodName === 'bundle') {
                            $val = $val->bundle ?? 'article';
                        } else {
                            return null;
                        }
                    } else {
                        return null;
                    }
                } else {
                    if (isset($val->$methodName)) {
                        $val = $val->$methodName;
                    } elseif ($val instanceof \SPPMod\Lekhak\Core\LekhakNode && $methodName === 'bundle') {
                        $val = $val->bundle ?? 'article';
                    } elseif (method_exists($val, 'get' . ucfirst($methodName))) {
                        $val = $val->{'get' . ucfirst($methodName)}();
                    } elseif (method_exists($val, 'get')) {
                        $val = $val->get($methodName);
                    } else {
                        return null;
                    }
                }
            } else {
                return null;
            }
        }
        return $val;
    }
}
