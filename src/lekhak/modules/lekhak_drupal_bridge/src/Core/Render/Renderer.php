<?php
namespace Lekhak\Modules\LekhakDrupalBridge\Core\Render;

class Renderer {
    public function render(&$elements, $is_root_call = false) {
        if (!is_array($elements)) {
            return (string) $elements;
        }

        $output = '';

        // If it's a form, wrap it
        $isForm = isset($elements['#type']) && $elements['#type'] === 'form';
        if ($isForm) {
            $output .= '<form method="POST" action="">';
        }

        // Process #theme
        if (isset($elements['#theme'])) {
            $output .= theme($elements['#theme'], $elements);
        }

        // Process #markup
        if (isset($elements['#markup'])) {
            $output .= $elements['#markup'];
        }

        // Process #type
        if (isset($elements['#type'])) {
            $type = $elements['#type'];
            $title = $elements['#title'] ?? '';
            $value = $elements['#default_value'] ?? $elements['#value'] ?? '';
            $name = $elements['#name'] ?? ''; // Need to extract key if not present
            
            // We usually need the array key for the name attribute. We pass it down recursively if possible.
            // For now, if we don't have a name, we might not render it correctly as a form input.
            
            $attributes = [];
            if ($name) $attributes[] = 'name="' . htmlspecialchars($name) . '"';
            if (isset($elements['#required']) && $elements['#required']) $attributes[] = 'required';

            if (isset($elements['#ajax'])) {
                $attributes[] = 'data-drupal-ajax="1"';
                if (isset($elements['#ajax']['callback'])) {
                    $cb = is_array($elements['#ajax']['callback']) ? implode('::', $elements['#ajax']['callback']) : $elements['#ajax']['callback'];
                    $attributes[] = 'data-ajax-callback="' . htmlspecialchars($cb) . '"';
                }
                if (isset($elements['#ajax']['wrapper'])) {
                    $attributes[] = 'data-ajax-wrapper="' . htmlspecialchars($elements['#ajax']['wrapper']) . '"';
                }
            }

            $attrStr = implode(' ', $attributes);

            if (!in_array($type, ['form', 'actions', 'container', 'details', 'fieldset', 'table'])) {
                $output .= '<div class="form-item">';
            }

            switch ($type) {
                case 'textfield':
                    if ($title) $output .= "<label>{$title}</label>";
                    $output .= "<input type=\"text\" value=\"" . htmlspecialchars($value) . "\" {$attrStr} />";
                    break;
                case 'textarea':
                    if ($title) $output .= "<label>{$title}</label>";
                    $output .= "<textarea {$attrStr}>" . htmlspecialchars($value) . "</textarea>";
                    break;
                case 'checkbox':
                    $checked = $value ? 'checked' : '';
                    $output .= "<label><input type=\"checkbox\" value=\"1\" {$attrStr} {$checked} /> {$title}</label>";
                    break;
                case 'select':
                    if ($title) $output .= "<label>{$title}</label>";
                    $output .= "<select {$attrStr}>";
                    if (isset($elements['#options'])) {
                        foreach ($elements['#options'] as $k => $v) {
                            $selected = ($k == $value) ? 'selected' : '';
                            $output .= "<option value=\"" . htmlspecialchars($k) . "\" {$selected}>" . htmlspecialchars($v) . "</option>";
                        }
                    }
                    $output .= "</select>";
                    break;
                case 'container':
                    $output .= "<div {$attrStr}>";
                    break;
                case 'details':
                case 'fieldset':
                    $output .= "<fieldset {$attrStr}>";
                    if ($title) $output .= "<legend>{$title}</legend>";
                    break;
                case 'table':
                    $output .= "<table {$attrStr}>";
                    if (isset($elements['#header'])) {
                        $output .= "<thead><tr>";
                        foreach ($elements['#header'] as $h) {
                            $output .= "<th>" . htmlspecialchars(is_array($h) ? ($h['data'] ?? '') : $h) . "</th>";
                        }
                        $output .= "</tr></thead>";
                    }
                    $output .= "<tbody>";
                    if (isset($elements['#rows'])) {
                        foreach ($elements['#rows'] as $r) {
                            $output .= "<tr>";
                            foreach ($r as $d) {
                                $output .= "<td>" . htmlspecialchars(is_array($d) ? ($d['data'] ?? '') : $d) . "</td>";
                            }
                            $output .= "</tr>";
                        }
                    }
                    $output .= "</tbody></table>";
                    break;
                case 'submit':
                    $output .= "<input type=\"submit\" value=\"" . htmlspecialchars($value) . "\" {$attrStr} />";
                    break;
            }

            if (!in_array($type, ['form', 'actions', 'container', 'details', 'fieldset', 'table'])) {
                $output .= '</div>';
            }
        }

        // Recurse children
        foreach ($elements as $key => $child) {
            // Ignore properties starting with #
            if (strpos($key, '#') === 0) continue;
            
            if (is_array($child)) {
                if (!isset($child['#name']) && isset($child['#type'])) {
                    $child['#name'] = $key;
                }
                $output .= $this->render($child, false);
            }
        }

        if (isset($elements['#type'])) {
            if ($elements['#type'] === 'container') {
                $output .= "</div>";
            } elseif (in_array($elements['#type'], ['details', 'fieldset'])) {
                $output .= "</fieldset>";
            }
        }

        if ($isForm) {
            $output .= '</form>';
        }

        return $output;
    }
}
