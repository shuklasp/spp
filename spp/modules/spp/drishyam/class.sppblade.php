<?php

namespace SPPMod\Drishyam;

use eftec\bladeone\BladeOne;

class SPPBladeEngine extends BladeOne
{
    public function runChild($view, $variables = []): string
    {
        if (is_array($variables) && !isset($variables['attributes'])) {
            $variables['attributes'] = new \SPPMod\Drishyam\ComponentAttributeBag($variables);
        }
        return parent::runChild($view, $variables);
    }

    public function runIsolated(string $view, array $variables = []): string
    {
        $backupSections = $this->sections;
        $backupViewStack = $this->viewStack;
        
        $this->sections = [];
        $this->viewStack = null;
        $output = $this->runInternal($view, $variables, false, false);
        
        $this->sections = $backupSections;
        $this->viewStack = $backupViewStack;
        return $output;
    }

    public function compileString($value): string
    {
        $value = $this->compileComponentTags($value);
        return parent::compileString($value);
    }

    /**
     * Compile declarative React/Vue-style component tags:
     * - <live:counter count="5" :user="$user" />
     * - <livewire:counter :count="$count" />
     * - <spp:table :data="$items" />
     */
    protected function compileComponentTags(string $value): string
    {
        // 1. <live:... /> and <livewire:... />
        $pattern = '/<(live|livewire):([a-zA-Z0-9_\-\.:]+)\s*([^>]*?)\s*(?:\/>|>(.*?)<\/\1:\2>)/s';
        $value = preg_replace_callback($pattern, function ($matches) {
            $component = $matches[2];
            $rawAttrs = $matches[3] ?? '';
            $slotContent = isset($matches[4]) ? trim($matches[4]) : null;

            $props = $this->parseTagAttributes($rawAttrs);

            if ($slotContent !== null && $slotContent !== '') {
                $slots = [];
                // Check for <slot:header>...</slot:header>, <x-slot:header>...</x-slot:header>,
                // <template #header>...</template>, or <template v-slot:header>...</template>
                $namedSlotPattern = '/<(?:(?:x-)?slot:([a-zA-Z0-9_\-]+)|template\s+#([a-zA-Z0-9_\-]+)|template\s+v-slot:([a-zA-Z0-9_\-]+))>(.*?)<\/(?:(?:x-)?slot:[a-zA-Z0-9_\-]+|template)>/s';

                $defaultContent = preg_replace_callback($namedSlotPattern, function ($slotMatch) use (&$slots) {
                    $slotName = !empty($slotMatch[1]) ? $slotMatch[1] : (!empty($slotMatch[2]) ? $slotMatch[2] : $slotMatch[3]);
                    $slots[$slotName] = trim($slotMatch[4]);
                    return '';
                }, $slotContent);

                $defaultContent = trim($defaultContent);
                if ($defaultContent !== '') {
                    $slots['default'] = $defaultContent;
                    $props['slot'] = var_export($defaultContent, true);
                }

                if (!empty($slots)) {
                    $slotsPhp = '[';
                    foreach ($slots as $sName => $sVal) {
                        $slotsPhp .= var_export($sName, true) . ' => ' . var_export($sVal, true) . ', ';
                        if (!isset($props[$sName]) && $sName !== 'default') {
                            $props[$sName] = var_export($sVal, true);
                        }
                    }
                    $slotsPhp .= ']';
                    $props['slots'] = $slotsPhp;
                }
            }

            $propsPhp = '[';
            foreach ($props as $k => $v) {
                $propsPhp .= var_export($k, true) . ' => ' . $v . ', ';
            }
            $propsPhp .= ']';

            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::live('{$component}', {$propsPhp}); ?>";
        }, $value);

        // 2. <spp:widget-name ... /> -> @sppwidget('widget-name', [...])
        $widgetPattern = '/<spp:([a-zA-Z0-9_\-]+)\s*([^>]*?)\s*(?:\/>|>(.*?)<\/spp:\1>)/s';
        $value = preg_replace_callback($widgetPattern, function ($matches) {
            $widget = $matches[1];
            $rawAttrs = $matches[2] ?? '';
            $slotContent = isset($matches[3]) ? trim($matches[3]) : null;
            $props = $this->parseTagAttributes($rawAttrs);

            if ($slotContent !== null && $slotContent !== '') {
                $slots = [];
                $namedSlotPattern = '/<(?:(?:x-)?slot:([a-zA-Z0-9_\-]+)|template\s+#([a-zA-Z0-9_\-]+)|template\s+v-slot:([a-zA-Z0-9_\-]+))>(.*?)<\/(?:(?:x-)?slot:[a-zA-Z0-9_\-]+|template)>/s';

                $defaultContent = preg_replace_callback($namedSlotPattern, function ($slotMatch) use (&$slots) {
                    $slotName = !empty($slotMatch[1]) ? $slotMatch[1] : (!empty($slotMatch[2]) ? $slotMatch[2] : $slotMatch[3]);
                    $slots[$slotName] = trim($slotMatch[4]);
                    return '';
                }, $slotContent);

                $defaultContent = trim($defaultContent);
                if ($defaultContent !== '') {
                    $slots['default'] = $defaultContent;
                    $props['slot'] = var_export($defaultContent, true);
                }

                if (!empty($slots)) {
                    $slotsPhp = '[';
                    foreach ($slots as $sName => $sVal) {
                        $slotsPhp .= var_export($sName, true) . ' => ' . var_export($sVal, true) . ', ';
                        if (!isset($props[$sName]) && $sName !== 'default') {
                            $props[$sName] = var_export($sVal, true);
                        }
                    }
                    $slotsPhp .= ']';
                    $props['slots'] = $slotsPhp;
                }
            }

            $propsPhp = '[';
            foreach ($props as $k => $v) {
                $propsPhp .= var_export($k, true) . ' => ' . $v . ', ';
            }
            $propsPhp .= ']';

            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('{$widget}', {$propsPhp}); ?>";
        }, $value);

        return $value;
    }

    /**
     * Parse HTML tag attributes supporting Vue/React dynamic bindings (:prop="expr").
     *
     * @param string $attributeString
     * @return array<string, string> Map of prop name to PHP expression string
     */
    protected function parseTagAttributes(string $attributeString): array
    {
        $props = [];
        $pattern = '/(?:\:([a-zA-Z0-9_\-]+)=(?:"([^"]*)"|\'([^\']*)\'))|([a-zA-Z0-9_\-]+)=(?:"([^"]*)"|\'([^\']*)\')|([a-zA-Z0-9_\-]+)/';

        if (preg_match_all($pattern, $attributeString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                // Dynamic binding: :prop="expr" or :prop='expr'
                if (!empty($match[1])) {
                    $propName = $match[1];
                    $expr = !empty($match[2]) ? $match[2] : ($match[3] ?? '');
                    $props[$propName] = '(' . $expr . ')';
                }
                // Static literal: prop="value" or prop='value'
                elseif (!empty($match[4])) {
                    $propName = $match[4];
                    $val = isset($match[5]) && $match[5] !== '' ? $match[5] : ($match[6] ?? '');
                    $props[$propName] = var_export($val, true);
                }
                // Boolean flag: disabled, required
                elseif (!empty($match[7])) {
                    $propName = $match[7];
                    $props[$propName] = 'true';
                }
            }
        }

        return $props;
    }
}

/**
 * Class SPPBlade
 * Wrapper for BladeOne engine in SPP.
 */
class SPPBlade extends \SPP\SPPObject
{
    protected ?BladeOne $engine = null;
    protected string $viewsPath;
    protected string $cachePath;
    protected string $appName;

    public function __construct(?string $appName = null)
    {
        if ($appName) {
            try {
                $app = new \SPP\App($appName);
            } catch (\Throwable $e) {
                $app = \SPP\App::getApp();
            }
        } else {
            $app = \SPP\App::getApp();
        }

        $this->appName = $app ? $app->getName() : ($appName ?: 'default');

        // Resolve paths relative to app source or base directory
        $srcDir = $app ? $app->getAppSrcDir() : (SPP_APP_DIR . '/src/' . $this->appName);
        if (is_dir($srcDir . '/resources/views')) {
            $this->viewsPath = $srcDir . '/resources/views';
        } else {
            $this->viewsPath = SPP_APP_DIR . '/resources/' . $this->appName . '/views';
        }

        $this->cachePath = SPP_APP_DIR . '/var/cache/' . $this->appName . '/blade';

        $this->ensureDirectories();

        $mode = 5; // Force MODE_DEBUG compilation to guarantee live CSS/layout loading

        $this->engine = new SPPBladeEngine($this->viewsPath, $this->cachePath, (int) $mode);

        $this->registerDirectives();
    }

    /**
     * Register custom SPP Blade directives.
     */
    protected function registerDirectives(): void
    {
        // @module_component('sppauth::login.form')
        // Cross-module component rendering support
        $this->engine->directive('module_component', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::module_component($expression); ?>";
        });

        // @sppform('login')
        // Loads form from XML/YAML in the app's forms directory
        $this->engine->directive('sppform', function ($expression) {
            if (empty($expression))
                return "";
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppform($expression); ?>";
        });

        // @sppform_start('login')
        $this->engine->directive('sppform_start', function ($expression) {
            if (empty($expression))
                return "";
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppform_start($expression); ?>";
        });

        // @sppform_end
        $this->engine->directive('sppform_end', function () {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppform_end(); ?>";
        });

        // @sppelement('username', ['class' => 'form-control'])
        $this->engine->directive('sppelement', function ($expression) {
            if (empty($expression))
                return "";
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppelement($expression); ?>";
        });

        // @sppform_render('contact', ['entity' => $user])
        $this->engine->directive('sppform_render', function ($expression) {
            if (empty($expression))
                return "";
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppform_render($expression); ?>";
        });

        // @sppreport('sales_summary', ['filters' => ['status' => 'active']])
        $this->engine->directive('sppreport', function ($expression) {
            if (empty($expression))
                return "";
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppreport($expression); ?>";
        });

        // @sppreport_chart('sales_summary', ['type' => 'bar', 'height' => 300])
        $this->engine->directive('sppreport_chart', function ($expression) {
            if (empty($expression))
                return "";
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppreport_chart($expression); ?>";
        });

        // @sppreport_pivot('sales_summary')
        $this->engine->directive('sppreport_pivot', function ($expression) {
            if (empty($expression))
                return "";
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppreport_pivot($expression); ?>";
        });

        // @sppreport_kpi('sales_summary', ['metric' => 'amount', 'label' => 'Total Revenue'])
        $this->engine->directive('sppreport_kpi', function ($expression) {
            if (empty($expression))
                return "";
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppreport_kpi($expression); ?>";
        });
        // @sppauth and @auth
        $authDirective = function () {
            return "<?php if (\SPPMod\SPPAuth\SPPAuth::authSessionExists()): ?>";
        };
        $this->engine->directive('sppauth', $authDirective);
        $this->engine->directive('auth', $authDirective);

        // @endsppauth and @endauth
        $endAuthDirective = function () {
            return "<?php endif; ?>";
        };
        $this->engine->directive('endsppauth', $endAuthDirective);
        $this->engine->directive('endauth', $endAuthDirective);

        // @sppguest and @guest
        $guestDirective = function () {
            return "<?php if (!\SPPMod\SPPAuth\SPPAuth::authSessionExists()): ?>";
        };
        $this->engine->directive('sppguest', $guestDirective);
        $this->engine->directive('guest', $guestDirective);

        // @endsppguest and @endguest
        $this->engine->directive('endsppguest', $endAuthDirective);
        $this->engine->directive('endguest', $endAuthDirective);

        // @csrf
        $this->engine->directive('csrf', function () {
            return '<?php echo \'<input type="hidden" name="csrf_token" value="\' . htmlspecialchars(\SPP\Core\Security::getCsrfToken() ?? \'\', ENT_QUOTES) . \'">\'; ?>';
        });

        // @method
        $this->engine->directive('method', function ($expression) {
            return '<?php echo \'<input type="hidden" name="_method" value="\' . htmlspecialchars(' . $expression . ', ENT_QUOTES) . \'">\'; ?>';
        });

        // @error and @spperror
        $errorDirective = function ($expression) {
            return "<?php if (\\SPPMod\\Drishyam\\TemplateMacros::hasError(\$errors ?? null, $expression)): \$message = \\SPPMod\\Drishyam\\TemplateMacros::getFirstError(\$errors ?? null, $expression); ?>";
        };
        $this->engine->directive('error', $errorDirective);
        $this->engine->directive('spperror', $errorDirective);

        // @enderror and @endspperror
        $endErrorDirective = function () {
            return "<?php endif; ?>";
        };
        $this->engine->directive('enderror', $endErrorDirective);
        $this->engine->directive('endspperror', $endErrorDirective);

        // @old and @sppold
        $oldDirective = function ($expression) {
            return "<?php echo htmlspecialchars((string)\\SPPMod\\Drishyam\\TemplateMacros::old($expression), ENT_QUOTES, 'UTF-8'); ?>";
        };
        $this->engine->directive('old', $oldDirective);
        $this->engine->directive('sppold', $oldDirective);

        // @class
        $this->engine->directive('class', function ($expression) {
            return 'class="<?php echo \SPPMod\Drishyam\TemplateMacros::toCssClasses(' . $expression . '); ?>"';
        });

        // @checked
        $this->engine->directive('checked', function ($expression) {
            return "<?php echo ($expression) ? 'checked=\"checked\"' : ''; ?>";
        });

        // @selected
        $this->engine->directive('selected', function ($expression) {
            return "<?php echo ($expression) ? 'selected=\"selected\"' : ''; ?>";
        });

        // @disabled
        $this->engine->directive('disabled', function ($expression) {
            return "<?php echo ($expression) ? 'disabled=\"disabled\"' : ''; ?>";
        });

        // @readonly
        $this->engine->directive('readonly', function ($expression) {
            return "<?php echo ($expression) ? 'readonly=\"readonly\"' : ''; ?>";
        });

        // @required
        $this->engine->directive('required', function ($expression) {
            return "<?php echo ($expression) ? 'required=\"required\"' : ''; ?>";
        });

        // @js
        $this->engine->directive('js', function ($expression) {
            return "<?php echo json_encode($expression, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>";
        });

        // @props(['type' => 'info', 'message'])
        $this->engine->directive('props', function ($expression) {
            return "<?php 
                \$__props = $expression;
                \$__keys = [];
                foreach (\$__props as \$__k => \$__v) {
                    \$__key = is_numeric(\$__k) ? \$__v : \$__k;
                    \$__keys[] = \$__key;
                    \$__default = is_numeric(\$__k) ? null : \$__v;
                    if (!isset(\${\$__key})) {
                        \${\$__key} = \$__default;
                    }
                }
                if (!isset(\$attributes)) {
                    \$attributes = new \SPPMod\Drishyam\ComponentAttributeBag();
                }
                \$attributes = \$attributes->exceptProps(\$__keys);
            ?>";
        });

        // @sppbind($entity)
        $this->engine->directive('sppbind', function ($expression) {
            if (empty($expression))
                return "";
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppbind($expression); ?>";
        });
        // @react('MyComponent', ['prop' => 'value'])
        $this->engine->directive('react', function ($expression) {
            if (empty($expression))
                return "";
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::react($expression); ?>";
        });

        // @vue('MyComponent', ['prop' => 'value'])
        $this->engine->directive('vue', function ($expression) {
            if (empty($expression))
                return "";
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::vue($expression); ?>";
        });

        // @sppux('ComponentName', ['prop' => 'value'])
        $this->engine->directive('sppux', function ($expression) {
            if (empty($expression))
                return "";
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppux($expression); ?>";
        });

        // @live('ComponentName', ['prop' => 'value'])
        $this->engine->directive('live', function ($expression) {
            if (empty($expression))
                return "";
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::live($expression); ?>";
        });

        // @livewire('ComponentName', ['prop' => 'value'])
        $this->engine->directive('livewire', function ($expression) {
            if (empty($expression))
                return "";
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::livewire($expression); ?>";
        });

        // @clientRules('field', $rules) and @rules('field', $rules)
        $this->engine->directive('clientRules', function ($expression) {
            return "<?php echo \\SPPMod\\SPPView\\Validation\\CustomRuleRegistry::toHtmlAttributes($expression); ?>";
        });
        $this->engine->directive('rules', function ($expression) {
            return "<?php echo \\SPPMod\\SPPView\\Validation\\CustomRuleRegistry::toHtmlAttributes($expression); ?>";
        });

        // @sppform_bind('form_id') and @sppform_rules('form_id')
        $this->engine->directive('sppform_bind', function ($expression) {
            return "<?php echo \\SPPMod\\SPPView\\Validation\\CustomRuleRegistry::renderFormSchemaScript($expression); ?>";
        });
        $this->engine->directive('sppform_rules', function ($expression) {
            return "<?php echo \\SPPMod\\SPPView\\Validation\\CustomRuleRegistry::renderFormSchemaScript($expression); ?>";
        });

        // @sppwidget('name', ['prop' => 'value'])
        $this->engine->directive('sppwidget', function ($expression) {
            if (empty($expression))
                return "";
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget($expression); ?>";
        });

        // Widget Shorthands
        $this->engine->directive('spptable', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('table', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppfilter', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('filter-builder', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppfileupload', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('file-upload', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('spptimeline', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('timeline', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppcode', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('code-block', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppmetricgrid', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('metric-grid', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppbadge', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('badge', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppempty', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('empty-state', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppdiff', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('diff', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('spptransfer', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('transfer', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppwizard', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('wizard', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppbreadcrumb', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('breadcrumb', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppdropdown', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('dropdown', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppdaterange', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('daterange', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('spptabs', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('tabs', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('spptree', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('tree', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppkanban', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('kanban', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppaccordion', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('accordion', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppcard', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('card', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('spplist', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('list', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppdescriptionlist', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('description-list', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppavatar', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('avatar', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppkbd', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('kbd', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('spprating', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('rating', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppslider', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('slider', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppcolorpicker', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('color-picker', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppswitch', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('switch', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppmarkdowneditor', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('markdown-editor', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppsignature', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('signature', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('spppagination', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('pagination', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppstepper', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('stepper', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppmodal', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('modal', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppdrawer', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('drawer', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppalert', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('alert', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppprogress', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('progress', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppskeleton', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('skeleton', " . ($expression ?: '[]') . "); ?>";
        });
        $this->engine->directive('sppqrcode', function ($expression) {
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppwidget('qrcode', " . ($expression ?: '[]') . "); ?>";
        });

        // @spppartial('view_name', ['prop' => 'value'])
        $this->engine->directive('spppartial', function ($expression) {
            if (empty($expression))
                return "";
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::spppartial($expression); ?>";
        });

        // @polyglot('service_name', ['prop' => 'value'], $fallback)
        $this->engine->directive('polyglot', function ($expression) {
            if (empty($expression))
                return "";
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::polyglot($expression); ?>";
        });

        // @polyglotpartial('python', 'service.py', 'func', ['data'])
        $this->engine->directive('polyglotpartial', function ($expression) {
            if (empty($expression))
                return "";
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::polyglotpartial($expression); ?>";
        });

        // @polyglot_eval('formula', 'expr', ['context'])
        $this->engine->directive('polyglot_eval', function ($expression) {
            if (empty($expression))
                return "";
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::polyglot_eval($expression); ?>";
        });

        // @sppcommandpalette
        $this->engine->directive('sppcommandpalette', function ($expression) {
            $expr = empty($expression) ? '[]' : $expression;
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::sppcommandpalette($expr); ?>";
        });

        // @spppwa
        $this->engine->directive('spppwa', function ($expression) {
            $expr = empty($expression) ? '[]' : $expression;
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::spppwa($expr); ?>";
        });

        // @region('region_name', ['context_key' => $val])
        $this->engine->directive('region', function ($expression) {
            if (empty($expression))
                return "";
            return "<?php echo \\SPPMod\\Drishyam\\TemplateMacros::region($expression); ?>";
        });

        // @route('students.show', ['id' => 1])
        $this->engine->directive('route', function ($expression) {
            return "<?php echo route($expression); ?>";
        });

        // @url('path/to/route')
        $this->engine->directive('url', function ($expression) {
            $expr = trim($expression);
            if (empty($expr) || $expr === "''" || $expr === '""') {
                return "<?php echo \\SPP\\App::getBaseUrl(\$app_name ?? \\SPP\\Scheduler::getContext()); ?>";
            }
            return "<?php echo \\SPP\\Core\\Url::to($expr, \$app_name ?? \\SPP\\Scheduler::getContext()); ?>";
        });

        // @external_url('github.com/foo') or @external_url($project['links']['source'])
        $this->engine->directive('external_url', function ($expression) {
            return "<?php echo \\SPP\\Core\\Url::external($expression); ?>";
        });

        // @externalUrl('github.com/foo')
        $this->engine->directive('externalUrl', function ($expression) {
            return "<?php echo \\SPP\\Core\\Url::external($expression); ?>";
        });

        // @drupal_node(123)
        $this->engine->directive('drupal_node', function ($expression) {
            return "<?php 
                \$drupal = \SPP\App::getApp()->make('drupal');
                \$node = \$drupal->getNode((int)$expression);
                if (\$node) {
                    echo \"<div class='drupal-node'>\";
                    echo \"<h3>\" . \$node->getTitle() . \"</h3>\";
                    echo \$node->get('body')->view('full');
                    echo \"</div>\";
                }
            ?>";
        });

        // @drupal_view('recent_news', 'block_1')
        $this->engine->directive('drupal_view', function ($expression) {
            return "<?php 
                \$drupal = \SPP\App::getApp()->make('drupal');
                if (\$drupal->bootstrap()) {
                    \$args = [$expression];
                    \$view = \Drupal\views\Views::getView(\$args[0]);
                    if (\$view) {
                        \$view->setDisplay(\$args[1] ?? 'default');
                        echo \$view->render();
                    }
                }
            ?>";
        });

        // @sppoffline('key')
        // Encapsulates template layout structures specifically cached for zero-latency offline client renders
        $this->engine->directive('sppoffline', function ($expression) {
            $key = trim($expression, " '\"");
            return "<?php echo \"<template data-spp-offline='\" . htmlspecialchars(\"{$key}\", ENT_QUOTES, 'UTF-8') . \"'>\"; ?>";
        });

        // @endsppoffline
        $this->engine->directive('endsppoffline', function () {
            return "<?php echo \"</template>\"; ?>";
        });

        // @load_node
        // Fully genericized context-aware entity binding adapting seamlessly to multi-tenant runtime targets
        $this->engine->directive('load_node', function () {
            return "<?php 
                if (empty(\$node)) {
                    \$__nodeId = \$_GET['id'] ?? (\$params[0] ?? null);
                    if (!empty(\$__nodeId)) {
                        try {
                            // Resolve specific database schema tables dynamically respecting global configuration mappings
                            \$__tbl = \\SPPMod\\SPPDB\\SPPDB::sppTable('nodes');
                            
                            \$__db = new \\SPPMod\\SPPDB\\SPPDB();
                            \$__res = \$__db->execute_query(\"SELECT * FROM {\$__tbl} WHERE id = ? OR alias = ?\", [\$__nodeId, \$__nodeId]);
                            if (!empty(\$__res)) {
                                \$node = (object)\$__res[0];
                            }
                        } catch (\Exception \$e) {}
                    }
                }
                if (empty(\$node)) {
                    \$node = (object)['title' => 'Live Declarative Node', 'body' => '<p>Ready-to-mount preview template fragment.</p>', 'created' => date('Y-m-d H:i:s')];
                }
            ?>";
        });

        // @load_page
        // Fully genericized dynamic landing page layout binding across disparate app namespaces
        $this->engine->directive('load_page', function () {
            return "<?php 
                if (empty(\$page)) {
                    \$__pageId = \$_GET['id'] ?? (\$params[0] ?? null);
                    if (!empty(\$__pageId)) {
                        try {
                            // Discover available framework or localized target models dynamically
                            \$__pageClass = '\\\\SPPMod\\\\Lekhak\\\\Core\\\\LandingPage';
                            \$__ctx = \SPP\Scheduler::getContext();
                            \$__appClass = \"\\\\App\\\\\" . ucfirst(\$__ctx) . \"\\\\Models\\\\LandingPage\";
                            
                            if (class_exists(\$__appClass)) {
                                \$__pageClass = \$__appClass;
                            }
                            if (class_exists(\$__pageClass) && method_exists(\$__pageClass, 'find_one')) {
                                \$page = \$__pageClass::find_one(is_numeric(\$__pageId) ? ['id' => \$__pageId] : ['alias' => \$__pageId]);
                            }
                        } catch (\Exception \$e) {}
                    }
                }
                if (empty(\$page)) {
                    \$page = (object)['title' => 'Live Scaffolding Screen Layout', 'alias' => 'preview-mode', 'id' => 1];
                }
            ?>";
        });
    }

    protected static array $instances = [];
    protected static ?self $instance = null;
    protected static array $renderingAppStack = [];

    /**
     * Get the app name of the currently rendering Blade template, if any.
     */
    public static function getCurrentRenderingApp(): ?string
    {
        return !empty(self::$renderingAppStack) ? end(self::$renderingAppStack) : null;
    }

    /**
     * Get singleton or app-specific instance.
     */
    public static function getInstance(?string $appName = null): self
    {
        $context = $appName ?: (class_exists('\\SPP\\Scheduler') ? \SPP\Scheduler::getContext() : null);
        if (!$context && class_exists('\\SPP\\App')) {
            try {
                $context = \SPP\App::getApp()->getName();
            } catch (\Throwable $e) {
                $context = 'default';
            }
        }
        $context = $context ?: 'default';

        if (!isset(self::$instances[$context])) {
            self::$instances[$context] = new self($context);
        }
        self::$instance = self::$instances[$context];
        return self::$instances[$context];
    }

    public static function reset(): void
    {
        self::$instances = [];
        self::$instance = null;
        self::$renderingAppStack = [];
    }

    /**
     * Static wrapper for rendering.
     */
    public static function render(string $view, array $data = [], ?string $appName = null): string
    {
        return self::getInstance($appName)->renderInstance($view, $data);
    }

    /**
     * Render an in-memory Blade template string with variables.
     * Enables React/Vue-style Single-File LiveComponents in pure PHP.
     *
     * @param string $template Raw Blade template string
     * @param array $data Data to extract into the template scope
     * @param string|null $appName Optional target app name
     * @return string Rendered HTML
     */
    public static function renderString(string $template, array $data = [], ?string $appName = null): string
    {
        $engine = self::getInstance($appName)->getEngine();
        if ($engine) {
            // BladeOne::runString does not reset captured sections unlike run(),
            // which causes section leakage and stale template content across successive string evaluations.
            try {
                $ref = new \ReflectionProperty($engine, 'sections');
                $ref->setValue($engine, []);
            } catch (\Throwable $t) {
                // Ignore if not accessible
            }
            return $engine->runString($template, $data);
        }
        return $template;
    }

    public function renderInstance(string $view, array $data = []): string
    {
        self::$renderingAppStack[] = $this->appName;
        try {
            return $this->doRenderInstance($view, $data);
        } finally {
            array_pop(self::$renderingAppStack);
        }
    }

    protected function doRenderInstance(string $view, array $data = []): string
    {
        @file_put_contents(SPP_LOG_DIR . '/debug_lekhak.log', "[" . date('Y-m-d H:i:s') . "] SPPBlade: Starting render for '$view'\n", FILE_APPEND);
        error_log("SPPBlade: Starting render for '$view'");

        // Support absolute paths
        if (file_exists($view) && str_ends_with($view, '.blade.php')) {

            // --- View Template Overriding ---
            // If the absolute path belongs to a module, check if the app overrides it
            $normalizedView = str_replace('\\', '/', $view);
            if (preg_match('#/modules/([^/]+)/([^/]+)/views/(.*)$#', $normalizedView, $matches)) {
                $modName = $matches[2];
                $viewPath = $matches[3];
                $overridePath = rtrim($this->viewsPath, '/\\') . '/modules/' . $modName . '/' . $viewPath;
                if (file_exists($overridePath)) {
                    $view = $overridePath;
                    @file_put_contents(SPP_LOG_DIR . '/debug_lekhak.log', "[" . date('Y-m-d H:i:s') . "] SPPBlade: Intercepted module view. Using override: '$overridePath'\n", FILE_APPEND);
                }
            }

            $dir = dirname($view);
            $viewName = basename($view, '.blade.php');

            // Build search paths:
            // 1. The directory containing the file (for immediate includes)
            // 2. The 'views' root of the theme (for layouts/extends)
            // 3. The application's base views (fallback)
            $paths = [$dir];

            $themeViewsRoot = $dir;
            $maxDepth = 5;
            while ($maxDepth > 0 && basename($themeViewsRoot) !== 'views' && dirname($themeViewsRoot) !== $themeViewsRoot) {
                $themeViewsRoot = dirname($themeViewsRoot);
                $maxDepth--;
            }
            if (basename($themeViewsRoot) === 'views' && !in_array($themeViewsRoot, $paths)) {
                $paths[] = $themeViewsRoot;
            }

            if (!in_array($this->viewsPath, $paths)) {
                $paths[] = $this->viewsPath;
            }

            // Set paths: prioritize the theme directory, then the default views
            $this->engine->setPath($paths, $this->cachePath);

            @file_put_contents(SPP_LOG_DIR . '/debug_lekhak.log', "[" . date('Y-m-d H:i:s') . "] SPPBlade: Rendering absolute file. ViewName: $viewName, Paths: " . json_encode($paths) . "\n", FILE_APPEND);

            try {
                $output = $this->engine->runIsolated($viewName, $data);
                @file_put_contents(SPP_LOG_DIR . '/debug_lekhak.log', "[" . date('Y-m-d H:i:s') . "] SPPBlade: Render successful for $viewName\n", FILE_APPEND);
                return $output;
            } catch (\Throwable $e) {
                $msg = "SPPBlade Template Error in '$viewName': " . $e->getMessage();
                @file_put_contents(SPP_LOG_DIR . '/debug_lekhak.log', "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
                
                if (class_exists('\\SPP\\SPPException')) {
                    $hint = "Check your Blade syntax in the file. Common issues include:\n"
                          . "- Unescaped special characters inside {{ }} or {!! !!}\n"
                          . "- Using raw PHP interpolation (like '\$var' in double quotes) instead of Blade directives\n"
                          . "- Missing commas or brackets in array arguments passed to @directives (e.g. @spppartial)";
                    throw new \SPP\SPPException($msg . "\n\n💡 Corrective Measure:\n" . $hint, 500, $e);
                }
                
                return "<div style='padding:1rem;background:#fee2e2;color:#991b1b;border:1px solid #ef4444;border-radius:4px;'><strong>🔥 Blade Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }

        // Build candidate search paths dynamically for relative views
        $searchPaths = [$this->viewsPath];

        // Also include the current active context views if different
        $currentContext = class_exists('\\SPP\\Scheduler') ? \SPP\Scheduler::getContext() : null;
        if ($currentContext && $currentContext !== $this->appName) {
            $contextViews = SPP_APP_DIR . '/src/' . $currentContext . '/resources/views';
            if (is_dir($contextViews) && !in_array($contextViews, $searchPaths)) {
                $searchPaths[] = $contextViews;
            }
        }

        // Framework and ecosystem fallbacks (e.g. SPPDocs views or global resources/views)
        $sppDocsViews = SPP_APP_DIR . '/src/SPPDocs/resources/views';
        if (is_dir($sppDocsViews) && !in_array($sppDocsViews, $searchPaths)) {
            $searchPaths[] = $sppDocsViews;
        }

        $globalViews = SPP_APP_DIR . '/resources/views';
        if (is_dir($globalViews) && !in_array($globalViews, $searchPaths)) {
            $searchPaths[] = $globalViews;
        }

        $this->engine->setPath($searchPaths, $this->cachePath);
        @file_put_contents(SPP_LOG_DIR . '/debug_lekhak.log', "[" . date('Y-m-d H:i:s') . "] SPPBlade: Rendering relative view '$view' with paths: " . json_encode($searchPaths) . "\n", FILE_APPEND);

        // Support full paths by stripping base view path if present (Legacy/Fallback)
        foreach ($searchPaths as $basePath) {
            if (strpos($view, $basePath) === 0) {
                $view = substr($view, strlen($basePath));
                break;
            }
        }

        // Normalize view identifier: strip extensions and convert directory slashes to dots for BladeOne
        $view = ltrim($view, '/\\');
        $view = str_replace('.blade.php', '', $view);
        $view = str_replace(['/', '\\'], '.', $view);

        try {
            return $this->engine->runIsolated($view, $data);
        } catch (\Throwable $e) {
            $msg = "SPPBlade Template Error in '$view': " . $e->getMessage();
            @file_put_contents(SPP_LOG_DIR . '/debug_lekhak.log', "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
            
            if (class_exists('\\SPP\\SPPException')) {
                $hint = "Check your Blade syntax in the file. Common issues include:\n"
                      . "- Unescaped special characters inside {{ }} or {!! !!}\n"
                      . "- Using raw PHP interpolation (like '\$var' in double quotes) instead of Blade directives\n"
                      . "- Missing commas or brackets in array arguments passed to @directives (e.g. @spppartial)";
                throw new \SPP\SPPException($msg . "\n\n💡 Corrective Measure:\n" . $hint, 500, $e);
            }
            
            return "<div style='padding:1rem;background:#fee2e2;color:#991b1b;border:1px solid #ef4444;border-radius:4px;'><strong>🔥 Blade Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    /**
     * Ensure views and cache directories exist.
     */
    protected function ensureDirectories(): void
    {
        if (!is_dir($this->viewsPath)) {
            mkdir($this->viewsPath, 0777, true);
        }
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0777, true);
        }
    }

    /**
     * Get the underlying engine instance.
     */
    public function getEngine(): BladeOne
    {
        return $this->engine;
    }

    /**
     * Compile a Blade template string to PHP code.
     */
    public function compileString(string $value): string
    {
        return $this->engine->compileString($value);
    }

    /**
     * Render a raw Blade template string with data.
     */
    public function renderRaw(string $template, array $data = []): string
    {
        return $this->engine->runString($template, $data);
    }

    /**
     * Add an additional view path for Blade to search.
     */
    public function addViewPath(string $path): void
    {
        // Use reflection to get and set the protected templatePath and compiledPath
        $reflection = new \ReflectionClass($this->engine);

        $tpProp = $reflection->getProperty('templatePath');
        $tpProp->setAccessible(true);
        $paths = $tpProp->getValue($this->engine);
        if (!is_array($paths)) {
            $paths = [$paths];
        }
        if (!in_array($path, $paths)) {
            array_unshift($paths, $path);
        }
        $tpProp->setValue($this->engine, $paths);
    }
}
