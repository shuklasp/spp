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

    public static function extPath(?string $appname = null): string
    {
        return self::toAppUri('spp/modules/spp/drishyam/js/sppext.js');
    }

    public static function exPath(?string $appname = null): string
    {
        return self::toAppUri('spp/modules/spp/drishyam/js/sppex.js');
    }

    public static function exProPath(?string $appname = null): string
    {
        return self::toAppUri('spp/modules/spp/drishyam/js/sppex-pro.js');
    }

    public static function exUltraPath(?string $appname = null): string
    {
        return self::toAppUri('spp/modules/spp/drishyam/js/sppex-ultra.js');
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
        \SPPMod\SPPView\ViewPage::addCssIncludeFile(self::toAppUri('spp/modules/spp/drishyam/css/sppext-premium.css'));

        // Add Runtime, UI Library, Grid, Bridge & Premium Extensions
        \SPPMod\SPPView\ViewPage::addJsIncludeFile(self::runtimePath($appname), ['type' => 'module']);
        \SPPMod\SPPView\ViewPage::addJsIncludeFile(self::uiPath($appname), ['type' => 'module']);
        \SPPMod\SPPView\ViewPage::addJsIncludeFile(self::gridPath($appname), ['type' => 'module']);
        \SPPMod\SPPView\ViewPage::addJsIncludeFile(self::bridgePath($appname), ['type' => 'module']);
        \SPPMod\SPPView\ViewPage::addJsIncludeFile(self::toAppUri('spp/modules/spp/drishyam/js/sppext.js'));

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
        const endpoint = window.SPP_CONFIG?.apiEndpoint || window.location.pathname;
        const ts = Date.now();
        const response = await fetch(`${endpoint}?action=${encodeURIComponent(action)}&t=${ts}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-SPP-Ajax': '1'
            },
            body: JSON.stringify(data),
            credentials: 'same-origin'
        });
        return response.json();
    },
    apiPost: async (actionOrFormData, data = {}) => {
        const endpoint = window.SPP_CONFIG?.apiEndpoint || window.location.pathname;
        const formData = actionOrFormData instanceof FormData ? actionOrFormData : new FormData();
        if (!(actionOrFormData instanceof FormData)) {
            formData.append('action', actionOrFormData);
            Object.entries(data).forEach(([key, value]) => formData.append(key, value));
        }
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-SPP-Ajax': '1'
            },
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

    /**
     * Render an SPPUX generic widget and enqueue required assets automatically.
     */
    public static function widget(string $name, array $props = [], ?string $appname = null): string
    {
        self::registerAssets($appname);
        return \SPPMod\Drishyam\TemplateMacros::sppwidget($name, $props);
    }

    public static function dataTable(array $columnsOrProps, array $data = [], array $options = []): string
    {
        if (isset($columnsOrProps['columns']) || isset($columnsOrProps['data']) || isset($columnsOrProps['title']) || empty($data)) {
            return self::widget('table', array_merge($columnsOrProps, $options));
        }
        return self::widget('table', array_merge(['columns' => $columnsOrProps, 'data' => $data], $options));
    }

    public static function filterBuilder(array $fieldsOrProps, array $options = []): string
    {
        if (isset($fieldsOrProps['fields']) || empty($fieldsOrProps) || !isset($fieldsOrProps[0])) {
            return self::widget('filter-builder', array_merge($fieldsOrProps, $options));
        }
        return self::widget('filter-builder', array_merge(['fields' => $fieldsOrProps], $options));
    }

    public static function fileUpload(array $options = []): string
    {
        return self::widget('file-upload', $options);
    }

    public static function timeline(array $itemsOrProps, array $options = []): string
    {
        if (isset($itemsOrProps['items']) || empty($itemsOrProps) || !isset($itemsOrProps[0])) {
            return self::widget('timeline', array_merge($itemsOrProps, $options));
        }
        return self::widget('timeline', array_merge(['items' => $itemsOrProps], $options));
    }

    public static function codeBlock(array|string $codeOrProps, string $language = 'text', array $options = []): string
    {
        if (is_array($codeOrProps)) {
            return self::widget('code-block', array_merge($codeOrProps, $options));
        }
        return self::widget('code-block', array_merge(['code' => $codeOrProps, 'language' => $language], $options));
    }

    public static function metricGrid(array $metricsOrProps, array $options = []): string
    {
        if (isset($metricsOrProps['metrics']) || empty($metricsOrProps) || !isset($metricsOrProps[0])) {
            return self::widget('metric-grid', array_merge($metricsOrProps, $options));
        }
        return self::widget('metric-grid', array_merge(['metrics' => $metricsOrProps], $options));
    }

    public static function badge(array|string $labelOrProps, array|string $variant = 'primary', bool $pulse = false, array $options = []): string
    {
        if (is_array($labelOrProps)) {
            return self::widget('badge', array_merge($labelOrProps, $options));
        }
        if (is_array($variant)) {
            return self::widget('badge', array_merge(['label' => $labelOrProps], $variant, $options));
        }
        return self::widget('badge', array_merge(['label' => $labelOrProps, 'variant' => $variant, 'pulse' => $pulse], $options));
    }

    public static function emptyState(array|string $titleOrProps, string $description = '', array $options = []): string
    {
        if (is_array($titleOrProps)) {
            return self::widget('empty-state', array_merge($titleOrProps, $options));
        }
        return self::widget('empty-state', array_merge(['title' => $titleOrProps, 'description' => $description], $options));
    }

    public static function diff(array|string $oldTextOrProps, string $newText = '', array $options = []): string
    {
        if (is_array($oldTextOrProps)) {
            return self::widget('diff', array_merge($oldTextOrProps, $options));
        }
        return self::widget('diff', array_merge(['oldText' => $oldTextOrProps, 'newText' => $newText], $options));
    }

    public static function transfer(array $itemsOrProps, array $selected = [], array $options = []): string
    {
        if (isset($itemsOrProps['items']) || isset($itemsOrProps['titles']) || empty($itemsOrProps) || !isset($itemsOrProps[0])) {
            return self::widget('transfer', array_merge($itemsOrProps, $options));
        }
        return self::widget('transfer', array_merge(['items' => $itemsOrProps, 'selected' => $selected], $options));
    }

    public static function wizard(array $stepsOrProps, array $options = []): string
    {
        if (isset($stepsOrProps['steps']) || empty($stepsOrProps) || !isset($stepsOrProps[0])) {
            return self::widget('wizard', array_merge($stepsOrProps, $options));
        }
        return self::widget('wizard', array_merge(['steps' => $stepsOrProps], $options));
    }

    public static function breadcrumb(array $itemsOrProps, array $options = []): string
    {
        if (isset($itemsOrProps['items']) || empty($itemsOrProps) || !isset($itemsOrProps[0])) {
            return self::widget('breadcrumb', array_merge($itemsOrProps, $options));
        }
        return self::widget('breadcrumb', array_merge(['items' => $itemsOrProps], $options));
    }

    public static function dropdown(array|string $triggerOrProps, array $items = [], array $options = []): string
    {
        if (is_array($triggerOrProps)) {
            return self::widget('dropdown', array_merge($triggerOrProps, $options));
        }
        return self::widget('dropdown', array_merge(['trigger' => $triggerOrProps, 'items' => $items], $options));
    }

    public static function dateRange(array $options = []): string
    {
        return self::widget('daterange', $options);
    }

    public static function tabs(array $tabsOrProps, array $options = []): string
    {
        if (isset($tabsOrProps['tabs']) || empty($tabsOrProps) || !isset($tabsOrProps[0])) {
            return self::widget('tabs', array_merge($tabsOrProps, $options));
        }
        return self::widget('tabs', array_merge(['tabs' => $tabsOrProps], $options));
    }

    public static function treeView(array $dataOrProps, array $options = []): string
    {
        if (isset($dataOrProps['data']) || empty($dataOrProps) || !isset($dataOrProps[0])) {
            return self::widget('tree', array_merge($dataOrProps, $options));
        }
        return self::widget('tree', array_merge(['data' => $dataOrProps], $options));
    }

    public static function kanban(array $columnsOrProps, array $options = []): string
    {
        if (isset($columnsOrProps['columns']) || empty($columnsOrProps) || !isset($columnsOrProps[0])) {
            return self::widget('kanban', array_merge($columnsOrProps, $options));
        }
        return self::widget('kanban', array_merge(['columns' => $columnsOrProps], $options));
    }

    public static function accordion(array $itemsOrProps, array $options = []): string
    {
        if (isset($itemsOrProps['items']) || empty($itemsOrProps) || !isset($itemsOrProps[0])) {
            return self::widget('accordion', array_merge($itemsOrProps, $options));
        }
        return self::widget('accordion', array_merge(['items' => $itemsOrProps], $options));
    }

    public static function card(array|string $titleOrProps, string $content = '', array $options = []): string
    {
        if (is_array($titleOrProps)) {
            return self::widget('card', array_merge($titleOrProps, $options));
        }
        return self::widget('card', array_merge(['title' => $titleOrProps, 'content' => $content], $options));
    }

    public static function listView(array $itemsOrProps, array $options = []): string
    {
        if (isset($itemsOrProps['items']) || empty($itemsOrProps) || !isset($itemsOrProps[0])) {
            return self::widget('list', array_merge($itemsOrProps, $options));
        }
        return self::widget('list', array_merge(['items' => $itemsOrProps], $options));
    }

    public static function descriptionList(array $itemsOrProps, array $options = []): string
    {
        if (isset($itemsOrProps['items']) || empty($itemsOrProps) || !isset($itemsOrProps[0])) {
            return self::widget('description-list', array_merge($itemsOrProps, $options));
        }
        return self::widget('description-list', array_merge(['items' => $itemsOrProps], $options));
    }

    public static function avatar(array|string $nameOrProps, array $options = []): string
    {
        if (is_array($nameOrProps)) {
            return self::widget('avatar', array_merge($nameOrProps, $options));
        }
        return self::widget('avatar', array_merge(['name' => $nameOrProps], $options));
    }

    public static function kbd(array|string $keysOrProps, array $options = []): string
    {
        if (is_array($keysOrProps)) {
            return self::widget('kbd', array_merge($keysOrProps, $options));
        }
        return self::widget('kbd', array_merge(['keys' => $keysOrProps], $options));
    }

    public static function rating(array|int|float $valueOrProps, int $max = 5, array $options = []): string
    {
        if (is_array($valueOrProps)) {
            return self::widget('rating', array_merge($valueOrProps, $options));
        }
        return self::widget('rating', array_merge(['value' => $valueOrProps, 'max' => $max], $options));
    }

    public static function slider(array|int|float $valueOrProps, int $min = 0, int $max = 100, array $options = []): string
    {
        if (is_array($valueOrProps)) {
            return self::widget('slider', array_merge($valueOrProps, $options));
        }
        return self::widget('slider', array_merge(['value' => $valueOrProps, 'min' => $min, 'max' => $max], $options));
    }

    public static function colorPicker(array|string $colorOrProps, array $options = []): string
    {
        if (is_array($colorOrProps)) {
            return self::widget('color-picker', array_merge($colorOrProps, $options));
        }
        return self::widget('color-picker', array_merge(['value' => $colorOrProps], $options));
    }

    public static function toggleSwitch(array|string $labelOrProps, bool $checked = false, array $options = []): string
    {
        if (is_array($labelOrProps)) {
            return self::widget('switch', array_merge($labelOrProps, $options));
        }
        return self::widget('switch', array_merge(['label' => $labelOrProps, 'checked' => $checked], $options));
    }

    public static function markdownEditor(array|string $contentOrProps, array $options = []): string
    {
        if (is_array($contentOrProps)) {
            return self::widget('markdown-editor', array_merge($contentOrProps, $options));
        }
        return self::widget('markdown-editor', array_merge(['value' => $contentOrProps], $options));
    }

    public static function signature(array $options = []): string
    {
        return self::widget('signature', $options);
    }

    public static function pagination(array|int $pageOrProps, int $totalPages = 1, array $options = []): string
    {
        if (is_array($pageOrProps)) {
            return self::widget('pagination', array_merge($pageOrProps, $options));
        }
        return self::widget('pagination', array_merge(['page' => $pageOrProps, 'totalPages' => $totalPages], $options));
    }

    public static function stepper(array $stepsOrProps, int $current = 1, array $options = []): string
    {
        if (isset($stepsOrProps['steps']) || empty($stepsOrProps) || !isset($stepsOrProps[0])) {
            return self::widget('stepper', array_merge($stepsOrProps, $options));
        }
        return self::widget('stepper', array_merge(['steps' => $stepsOrProps, 'current' => $current], $options));
    }

    public static function modal(array|string $titleOrProps, string $content = '', array $options = []): string
    {
        if (is_array($titleOrProps)) {
            return self::widget('modal', array_merge($titleOrProps, $options));
        }
        return self::widget('modal', array_merge(['title' => $titleOrProps, 'content' => $content], $options));
    }

    public static function drawer(array|string $titleOrProps, string $content = '', array $options = []): string
    {
        if (is_array($titleOrProps)) {
            return self::widget('drawer', array_merge($titleOrProps, $options));
        }
        return self::widget('drawer', array_merge(['title' => $titleOrProps, 'content' => $content], $options));
    }

    public static function alert(array|string $messageOrProps, string $variant = 'info', array $options = []): string
    {
        if (is_array($messageOrProps)) {
            return self::widget('alert', array_merge($messageOrProps, $options));
        }
        return self::widget('alert', array_merge(['message' => $messageOrProps, 'variant' => $variant], $options));
    }

    public static function progress(array|int|float $valueOrProps, array $options = []): string
    {
        if (is_array($valueOrProps)) {
            return self::widget('progress', array_merge($valueOrProps, $options));
        }
        return self::widget('progress', array_merge(['value' => $valueOrProps], $options));
    }

    public static function skeleton(array|string $variantOrProps = 'text', array $options = []): string
    {
        if (is_array($variantOrProps)) {
            return self::widget('skeleton', array_merge($variantOrProps, $options));
        }
        return self::widget('skeleton', array_merge(['variant' => $variantOrProps], $options));
    }

    public static function qrCode(array|string $dataOrProps, array $options = []): string
    {
        if (is_array($dataOrProps)) {
            return self::widget('qrcode', array_merge($dataOrProps, $options));
        }
        return self::widget('qrcode', array_merge(['data' => $dataOrProps], $options));
    }
}
