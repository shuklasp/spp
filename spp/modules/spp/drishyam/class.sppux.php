<?php

namespace SPPMod\Drishyam;

/**
 * SPP-UX module facade.
 *
 * Provides shared helpers for registering the native SPP frontend runtime
 * and rendering mount points for application components.
 */
class SPPUX extends \SPP\SPPObject
{
    private static function appBaseUri(): string
    {
        return defined('APP_BASE_URI') ? APP_BASE_URI : '';
    }

    private static function toAppUri(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || preg_match('/^https?:\/\//i', $path) || str_starts_with($path, '/') || str_starts_with($path, './') || str_starts_with($path, '../')) {
            return $path;
        }

        return rtrim(self::appBaseUri(), '/') . '/' . ltrim($path, '/');
    }

    public static function runtimePath(?string $appname = null): string
    {
        $value = \SPP\Module::getConfig('runtime_path', 'sppux', $appname);
        return self::toAppUri($value ?: 'spp/modules/spp/drishyam/js/sppux.js');
    }

    public static function uiPath(?string $appname = null): string
    {
        $value = \SPP\Module::getConfig('ui_path', 'sppux', $appname);
        return self::toAppUri($value ?: 'spp/modules/spp/drishyam/js/sppux-ui.js');
    }

    public static function cssPath(?string $appname = null): string
    {
        $value = \SPP\Module::getConfig('css_path', 'sppux', $appname);
        return self::toAppUri($value ?: 'spp/modules/spp/drishyam/css/sppux.css');
    }

    public static function gridPath(?string $appname = null): string
    {
        $value = \SPP\Module::getConfig('grid_path', 'sppux', $appname);
        return self::toAppUri($value ?: 'spp/modules/spp/drishyam/js/sppux-grid.js');
    }

    public static function bridgePath(?string $appname = null): string
    {
        $value = \SPP\Module::getConfig('bridge_path', 'sppux', $appname);
        return self::toAppUri($value ?: 'spp/modules/spp/drishyam/js/sppux-bridge.js');
    }

    public static function loaderPath(?string $appname = null): string
    {
        $value = \SPP\Module::getConfig('loader_path', 'sppux', $appname);
        return self::toAppUri($value ?: 'spp/modules/spp/drishyam/js/spp-loader.js');
    }

    public static function componentBase(?string $appname = null): string
    {
        $appname = $appname ?: \SPP\Scheduler::getContext();
        $value = \SPP\Module::getConfig('component_base', 'sppux', $appname);
        $value = $value ?: 'src/{app}/comp';
        return self::toAppUri(str_replace('{app}', $appname, $value));
    }

    public static function componentPath(string $name, ?string $appname = null): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new \SPP\SPPException('SPP-UX component name cannot be empty.');
        }

        if (preg_match('/^https?:\/\//i', $name) || str_starts_with($name, '/') || str_starts_with($name, './') || str_starts_with($name, '../')) {
            return $name;
        }

        return rtrim(self::componentBase($appname), '/') . '/' . ltrim($name, '/') . '.js';
    }

    /**
     * Register SPP-UX assets to the current ViewPage.
     */
    public static function registerAssets(?string $appname = null): void
    {
        if (!class_exists('\SPPMod\SPPView\ViewPage', true)) {
            return;
        }

        // Check if disabled in config
        if (\SPP\Module::getConfig('disabled', 'sppux', $appname)) {
            return;
        }

        // Add Core CSS
        \SPPMod\SPPView\ViewPage::addCssIncludeFile(self::cssPath($appname));

        // Add Runtime, UI Library, Grid & Bridge
        \SPPMod\SPPView\ViewPage::addJsIncludeFile(self::runtimePath($appname), ['type' => 'module']);
        \SPPMod\SPPView\ViewPage::addJsIncludeFile(self::uiPath($appname), ['type' => 'module']);
        \SPPMod\SPPView\ViewPage::addJsIncludeFile(self::gridPath($appname), ['type' => 'module']);
        \SPPMod\SPPView\ViewPage::addJsIncludeFile(self::bridgePath($appname), ['type' => 'module']);

        // Add Loader if auto_mount is enabled
        $autoMount = \SPP\Module::getConfig('auto_mount', 'sppux', $appname);
        if ($autoMount !== false && $autoMount !== 'false' && $autoMount !== '0') {
            \SPPMod\SPPView\ViewPage::addJsIncludeFile(self::loaderPath($appname), ['type' => 'module']);
        }
    }

    public static function registerBridge(?string $appname = null): void
    {
        if (!class_exists('\SPPMod\SPPView\ViewPage', true)) {
            return;
        }

        $exposeBridge = \SPP\Module::getConfig('expose_bridge', 'sppux', $appname);
        if ($exposeBridge === false || $exposeBridge === 'false' || $exposeBridge === '0') {
            return;
        }

        \SPPMod\SPPView\ViewPage::addJsContent(<<<'JS'
window.spp_admin = window.spp_admin || {
    api: async (action, data = {}) => {
        const params = new URLSearchParams({ action, ...data });
        const response = await fetch('api.php?' + params.toString(), { credentials: 'same-origin' });
        return response.json();
    },
    apiPost: async (actionOrFormData, data = {}) => {
        const formData = actionOrFormData instanceof FormData ? actionOrFormData : new FormData();
        if (!(actionOrFormData instanceof FormData)) {
            formData.append('action', actionOrFormData);
            Object.entries(data).forEach(([key, value]) => formData.append(key, value));
        }
        const response = await fetch('api.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        return response.json();
    },
    callAppService: async (name, params = {}) => {
        const formData = new FormData();
        formData.append('params', JSON.stringify(params));
        const response = await fetch(`?__spa=1&__svc=${encodeURIComponent(name)}`, {
            method: 'POST',
            headers: { 'X-SPP-Ajax': '1' },
            body: formData,
            credentials: 'same-origin'
        });
        const result = await response.json();
        if (result.status === 'ok' || result.success === true) {
            return result.data !== undefined ? result.data : result;
        }
        throw new Error(result.message || result.data?.message || 'SPP service call failed.');
    },
    streamService: (name, params = {}, onMessage = null, onError = null) => {
        const urlParams = new URLSearchParams({ __spa_stream: name, ...params });
        const source = new EventSource('?' + urlParams.toString());
        if (onMessage) {
            ['start', 'progress', 'complete'].forEach(evt => {
                source.addEventListener(evt, e => {
                    try { onMessage(evt, JSON.parse(e.data)); } catch(err) {}
                    if (evt === 'complete') source.close();
                });
            });
        }
        source.addEventListener('error', e => {
            if (onError) onError(e);
            source.close();
        });
        return source;
    },
    securePayload: async (plainObject) => {
        if (!window.crypto || !window.crypto.subtle) return plainObject;
        try {
            const encoded = new TextEncoder().encode(JSON.stringify(plainObject));
            const hash = await window.crypto.subtle.digest('SHA-256', encoded);
            const hashHex = Array.from(new Uint8Array(hash)).map(b => b.toString(16).padStart(2, '0')).join('');
            return { __secure: true, digest: hashHex, payload: plainObject };
        } catch (e) {
            return plainObject;
        }
    }
};
JS);
    }

    public static function boot(?string $appname = null): void
    {
        self::registerAssets($appname);
        self::registerBridge($appname);
    }

    public static function component(string $name, array $props = [], ?string $appname = null): string
    {
        $path = self::componentPath($name, $appname);

        // Optional SSR content if provided in props under '__ssr'
        $ssrContent = $props['__ssr'] ?? '';
        unset($props['__ssr']);

        // Optional embedded declarative template if provided under '__template'
        $templateContent = $props['__template'] ?? '';
        unset($props['__template']);

        // Optional Reactivity Islands partial hydration behavior strategy descriptor ('visible', 'idle', 'media')
        $islandMode = $props['__island'] ?? '';
        unset($props['__island']);

        $propsJson = htmlspecialchars(json_encode($props), ENT_QUOTES, 'UTF-8');
        $pathAttr = htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
        $islandAttr = $islandMode ? ' data-spp-island="' . htmlspecialchars($islandMode, ENT_QUOTES, 'UTF-8') . '"' : '';

        $innerTemplate = $templateContent ? "<template data-spp-template=\"1\">{$templateContent}</template>" : '';

        return "<div data-spp-component=\"1\" data-spp-type=\"ux\" data-spp-path=\"{$pathAttr}\" data-spp-props=\"{$propsJson}\"{$islandAttr}>{$ssrContent}{$innerTemplate}</div>";
    }

    public static function render(string $name, array $props = [], ?string $appname = null): void
    {
        echo self::component($name, $props, $appname);
    }
}
