<?php

namespace SPPMod\SPPView;

/**
 * class ViewFormTheme
 * Handles the visual presentation of form elements.
 */
abstract class ViewFormTheme
{
    abstract public function renderGroup(SPPViewForm_Element $elem): string;

    public static function getTheme(string $name = 'default'): ViewFormTheme
    {
        return match ($name) {
            'bootstrap'   => new BootstrapTheme(),
            'tailwind'    => new TailwindTheme(),
            'glass_admin' => new GlassAdminTheme(),
            default       => new DefaultTheme(),
        };
    }

    protected function getColClass(int $cols): string
    {
        return "spp-col-{$cols}";
    }
}

/**
 * The standard SPP/Bootstrap-like theme.
 */
class DefaultTheme extends ViewFormTheme
{
    public function renderGroup(SPPViewForm_Element $elem): string
    {
        $id = $elem->getAttribute('id');
        $name = $elem->getAttribute('name');
        $errorId = 'err_' . ($id ?: $name);
        $col = $elem->getAttribute('col') ?: 12;

        $html = '<div class="spp-form-group ' . $this->getColClass((int)$col) . '" id="group_' . $id . '">';

        if ($elem->getLabel()) {
            $html .= '<label class="spp-label" for="' . $id . '">' . $elem->getLabel() . '</label>';
        }

        $html .= '<div class="spp-input-wrapper">';
        $html .= $elem->renderRaw();

        if ($elem->getAttribute('help')) {
            $html .= '<small class="spp-help-text">' . $elem->getAttribute('help') . '</small>';
        }

        $html .= '<div class="spp-error-msg" id="' . $errorId . '"></div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}

class BootstrapTheme extends ViewFormTheme
{
    public function renderGroup(SPPViewForm_Element $elem): string
    {
        $id = $elem->getAttribute('id');
        $col = $elem->getAttribute('col') ?: 12;
        $html = '<div class="mb-3 col-md-' . $col . '" id="group_' . $id . '">';
        if ($elem->getLabel()) {
            $html .= '<label class="form-label" for="' . $id . '">' . $elem->getLabel() . '</label>';
        }
        $elem->addClass('form-control');
        $html .= $elem->renderRaw();
        $html .= '<div class="invalid-feedback" id="err_' . $id . '"></div>';
        $html .= '</div>';
        return $html;
    }
}

class TailwindTheme extends ViewFormTheme
{
    public function renderGroup(SPPViewForm_Element $elem): string
    {
        $id = $elem->getAttribute('id');
        $col = $elem->getAttribute('col') ?: 12;
        // Tailwind grid spans (assuming 12 col grid)
        $span = "col-span-{$col}";
        if ($col == 12) {
            $span = "col-span-full";
        }

        $html = '<div class="flex flex-col gap-1 mb-4 ' . $span . '" id="group_' . $id . '">';
        if ($elem->getLabel()) {
            $html .= '<label class="text-sm font-medium text-gray-700" for="' . $id . '">' . $elem->getLabel() . '</label>';
        }
        $elem->addClass('border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500 outline-none');
        $html .= $elem->renderRaw();
        $html .= '<p class="text-xs text-red-500 mt-1" id="err_' . $id . '"></p>';
        $html .= '</div>';
        return $html;
    }
}

/**
 * Premium Glassmorphism theme for the SPP Admin Panel.
 */
class GlassAdminTheme extends ViewFormTheme
{
    public function renderGroup(SPPViewForm_Element $elem): string
    {
        $id = $elem->getAttribute('id');
        $name = $elem->getAttribute('name');
        $errorId = 'err_' . ($id ?: $name);
        $col = $elem->getAttribute('col') ?: 12;

        $html = '<div class="spp-form-group ' . $this->getColClass((int)$col) . '" id="group_' . $id . '" style="margin-bottom: 20px;">';

        if ($elem->getLabel()) {
            $label = $elem->getLabel();
            if ($elem->getAttribute('required')) {
                $label .= ' <span style="color: var(--danger);">*</span>';
            }
            $html .= '<label class="spp-label" for="' . $id . '" style="display: block; margin-bottom: 8px; font-size: 0.85rem; font-weight: 500; color: var(--text-secondary);">' . $label . '</label>';
        }

        $html .= '<div class="spp-input-wrapper">';

        // Use system design tokens for adaptive visibility across Night/Day/Saffron modes
        $elem->setAttribute('style', ($elem->getAttribute('style') ?: '') . ' width: 100%; padding: 10px; background: var(--input-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-md); color: var(--text-main); font-size: 0.95rem; transition: all 0.2s ease;');

        $html .= $elem->renderRaw();

        if ($elem->getAttribute('help')) {
            $html .= '<small class="spp-help-text" style="display: block; margin-top: 5px; font-size: 0.75rem; color: var(--text-dim);">' . $elem->getAttribute('help') . '</small>';
        }

        $html .= '<div class="spp-error-msg" id="' . $errorId . '" style="color: var(--danger); font-size: 0.75rem; margin-top: 5px;"></div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}
