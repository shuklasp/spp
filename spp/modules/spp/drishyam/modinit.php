<?php

/**
 * SPP-UX Module Initialization
 *
 * Automatically boots the SPP-UX runtime and bridge if active.
 */
\SPP\SPPEvent::listen('event_spp_kernel_boot', function () {
    \SPPMod\Drishyam\SPPUX::boot();
    \SPPMod\Drishyam\Sppext::boot();
});

/**
 * Listen to standard ViewController render event to inject Blade/Twig rendering logic.
 */
\SPP\SPPEvent::listen('spp.controller.render', function (\SPP\EventParams $params) {
    if ($params->get('handled')) {
        return;
    }

    $view = $params->get('view');
    $data = $params->get('data');
    $engine = $params->get('engine');
    $app = $params->get('app') ?: (class_exists('\\SPP\\Scheduler') ? \SPP\Scheduler::getContext() : 'Samvaad');

    if ($engine === 'twig') {
        $params->set('output', \SPPMod\Drishyam\SPPTwig::render($view, $data));
        $params->set('handled', true);
        $params->stopPropagation();
        return;
    }

    if ($engine === 'php') {
        return;
    }

    if ($engine === null && class_exists('\\SPPMod\\SPPView\\ViewLocator') && !empty($app)) {
        $located = \SPPMod\SPPView\ViewLocator::locate($view, $app);
        if ($located && !str_ends_with($located, '.blade.php') && (str_ends_with($located, '.php') || str_ends_with($located, '.html'))) {
            // It is a native PHP/HTML page, not a Blade template. Let ViewController handle it via ViewLocator.
            return;
        }
    }

    if ($engine === 'php' || (is_string($view) && str_ends_with($view, '.php') && !str_ends_with($view, '.blade.php'))) {
        // Native PHP file — do not intercept with Blade, let ViewController handle it via ViewLocator
        return;
    }

    if ($engine === 'blade' || $engine === null) {
        $params->set('output', \SPPMod\Drishyam\SPPBlade::render($view, $data));
        $params->set('handled', true);
        $params->stopPropagation();
        return;
    }
});
