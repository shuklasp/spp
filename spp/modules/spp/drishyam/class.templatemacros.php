<?php

namespace SPPMod\Drishyam;

/**
 * TemplateMacros
 * Single source of truth for runtime execution of template directives.
 * Used by both Blade and Twig engines.
 */
class TemplateMacros
{
    public static function module_component(string $viewStr, array $data = []): string
    {
        ob_start();
        if (strpos($viewStr, '::') !== false) {
            list($modName, $viewFile) = explode('::', $viewStr);
            $modClass = \SPP\ModuleCompiler::getActiveModuleClass($modName);
            if ($modClass) {
                $modInstance = \SPP\Module::getModule($modClass);
                if ($modInstance) {
                    $path = $modInstance->getModuleDir() . '/views/' . str_replace('.', '/', $viewFile) . '.blade.php';
                    if (file_exists($path)) {
                        // Currently hardcoded to Blade for module components, but could be dynamic
                        echo \SPPMod\Drishyam\SPPBlade::renderInstance($viewFile, $data);
                    }
                }
            }
        }
        return ob_get_clean() ?: '';
    }

    public static function sppform(string $fname): string
    {
        $appName = \SPP\Scheduler::getContext();
        $fname = str_replace(['\'', '"'], '', $fname);
        $app = \SPP\App::getApp($appName);
        $baseDir = $app->getAppConfDir() . '/forms/';
        
        $formFile = null;
        foreach (['yml', 'yaml', 'xml'] as $ext) {
            if (file_exists($baseDir . $fname . '.' . $ext)) {
                $formFile = $baseDir . $fname . '.' . $ext;
                break;
            }
        }

        if ($formFile) {
            \SPPMod\SPPView\ViewPage::readFormFile($formFile);
        }
        return '';
    }

    public static function sppform_start(string $fname): string
    {
        $forms = \SPPMod\SPPView\ViewPage::getFormsList();
        $fname = str_replace(['\'', '"'], '', $fname);
        if (isset($forms[$fname])) {
            $forms[$fname]->startForm();
        }
        return '';
    }

    public static function sppform_end(): string
    {
        $forms = \SPPMod\SPPView\ViewPage::getFormsList();
        $activeForm = end($forms);
        if ($activeForm) {
            $activeForm->endForm();
        }
        return '';
    }

    public static function sppelement(string $elemId, array $attrs = []): string
    {
        ob_start();
        $forms = \SPPMod\SPPView\ViewPage::getFormsList();
        foreach ($forms as $form) {
            $elements = $form->get('element');
            if (isset($elements[$elemId])) {
                $el = $elements[$elemId];
                if (!empty($attrs) && method_exists($el, 'setAttributes')) {
                    $el->setAttributes($attrs);
                }
                echo $el->getHTML();
                break;
            }
        }
        return ob_get_clean() ?: '';
    }

    public static function sppauth(): bool
    {
        return \SPPMod\SPPAuth\SPPAuth::authSessionExists();
    }

    public static function sppbind($entity): string
    {
        $forms = \SPPMod\SPPView\ViewPage::getFormsList();
        $activeForm = end($forms);
        if ($activeForm && isset($entity)) {
            $activeForm->bind($entity);
        }
        return '';
    }

    public static function react(string $name, array $props = []): string
    {
        $propsJson = json_encode($props);
        $context = \SPP\Scheduler::getContext();
        $app = \SPP\App::getApp($context);
        $srcPath = \SPP\App::getAppConf('src_path', $context) ?? ('src/' . $context);
        $path = "/{$srcPath}/resources/js/react/{$name}.js";
        return "<div data-spp-component='1' data-spp-type='react' data-spp-path='{$path}' data-spp-props='" . htmlspecialchars($propsJson, ENT_QUOTES, 'UTF-8') . "'></div>";
    }

    public static function vue(string $name, array $props = []): string
    {
        $propsJson = json_encode($props);
        $context = \SPP\Scheduler::getContext();
        $app = \SPP\App::getApp($context);
        $srcPath = \SPP\App::getAppConf('src_path', $context) ?? ('src/' . $context);
        $path = "/{$srcPath}/resources/js/vue/{$name}.js";
        return "<div data-spp-component='1' data-spp-type='vue' data-spp-path='{$path}' data-spp-props='" . htmlspecialchars($propsJson, ENT_QUOTES, 'UTF-8') . "'></div>";
    }

    public static function sppux(string $name, array $props = []): string
    {
        if (class_exists('\\SPPMod\\SPPUX\\SPPUX')) {
            // Forward compatibility if SPPUX ever moves to its own module
            return \SPPMod\Drishyam\SPPUX::component($name, $props);
        } else {
            $propsJson = htmlspecialchars(json_encode($props), ENT_QUOTES, 'UTF-8');
            $context = \SPP\Scheduler::getContext();
            $appBaseUri = defined('APP_BASE_URI') ? APP_BASE_URI : '';
            $path = rtrim($appBaseUri, '/') . "/src/{$context}/comp/{$name}.js";
            return "<div data-spp-component='1' data-spp-type='ux' data-spp-path='{$path}' data-spp-props='{$propsJson}'></div>";
        }
    }
}
