<?php
/**
 * ============================================================================
 * FinalTest — SPP-UX Single Page Application Entry Point
 * ============================================================================
 *
 * HOW THIS WORKS:
 * This file is included by the SPP ViewRouter as a "special: 1" page.
 * Special pages bypass the augmentation pipeline (no auto JS/CSS injection)
 * because they provide their own complete HTML document.
 *
 * The SPP framework is ALREADY BOOTED when this file runs:
 *   - Do NOT add: require_once 'sppinit.php'   (already loaded)
 *   - Do NOT add: \SPP\App::getApp()            (context already set)
 *
 * SPP-UX RUNTIME ASSETS (loaded via PHP helpers):
 *   - SPPUX::runtimePath()  → Core reactive engine (sppux.js)
 *   - SPPUX::uiPath()       → UI Library: Modal, Toast, Drawer, Spotlight
 *   - SPPUX::cssPath()      → Glassmorphic CSS with 7 built-in themes
 *   - SPPUX::loaderPath()   → Auto-mounts components with data-spp-component
 *   - SPPUX::bridgePath()   → PHP↔JS bridge for API/service calls
 *
 * HOW TO MODIFY:
 *   - Change the mounted component: edit data-spp-path attribute below
 *   - Add more components: add more data-spp-component divs
 *   - Switch theme: SPPUX.Theme.set('midnight'|'emerald'|'cyberpunk'|'ocean'|'saffron'|'day')
 * ============================================================================
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo 'FinalTest'; ?> — SPP-UX Application</title>

    <!-- SPP-UX Glassmorphic CSS (includes all theme presets) -->
    <link rel="stylesheet" href="<?php echo \SPPMod\Drishyam\SPPUX::cssPath(); ?>">

    <!-- Google Fonts for premium typography -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ── App-level CSS overrides ─────────────────────────────────
         * Override SPP-UX CSS variables here for custom branding.
         * See the full list in spp/modules/spp/drishyam/css/sppux.css
         */
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        /* Loading state while SPP-UX runtime boots */
        .spp-app-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: var(--sppux-bg, #0f0f23);
            color: var(--sppux-text, #e2e8f0);
            font-family: 'Inter', sans-serif;
        }
        .spp-app-loading .spinner {
            width: 40px; height: 40px;
            border: 3px solid rgba(99,102,241,0.2);
            border-top-color: #6366f1;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-right: 1rem;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <!--
        ═══════════════════════════════════════════════════════════════
        SPP-UX Component Mount Point

        HOW IT WORKS:
        The SPP-UX loader (spp-loader.js) scans the DOM for elements
        with data-spp-component="1" and auto-mounts them.

        ATTRIBUTES:
          data-spp-component="1"  — Marks this as a mount point
          data-spp-type="ux"      — Component type (always "ux" for SPP-UX)
          data-spp-path="..."     — Path to the component JS file
          data-spp-props='...'    — JSON-encoded props passed to the component

        TO ADD MORE COMPONENTS:
        Just add more divs with these attributes anywhere in the HTML.
        ═══════════════════════════════════════════════════════════════
    -->
    <div data-spp-component="1"
         data-spp-type="ux"
         data-spp-path="<?php echo \SPPMod\Drishyam\SPPUX::componentPath('main'); ?>"
         data-spp-props='{"appName":"<?php echo 'FinalTest'; ?>"}'>
        <!-- This content shows while the component loads -->
        <div class="spp-app-loading">
            <div class="spinner"></div>
            <span>Loading <?php echo 'FinalTest'; ?>...</span>
        </div>
    </div>

    <!-- ═══ SPP-UX Runtime Scripts ═══ -->

    <!-- Core reactive engine: BaseComponent, html``, setState, render cycle -->
    <script type="module" src="<?php echo \SPPMod\Drishyam\SPPUX::runtimePath(); ?>"></script>

    <!-- UI Library: SPPUX.Modal, SPPUX.Notify, SPPUX.Confirm, SPPUX.Theme, etc. -->
    <script type="module" src="<?php echo \SPPMod\Drishyam\SPPUX::uiPath(); ?>"></script>

    <!-- PHP↔JS Bridge: spp_admin.api(), spp_admin.callAppService(), etc. -->
    <script type="module" src="<?php echo \SPPMod\Drishyam\SPPUX::bridgePath(); ?>"></script>

    <!-- Auto-mounter: scans DOM for data-spp-component and instantiates them -->
    <script type="module" src="<?php echo \SPPMod\Drishyam\SPPUX::loaderPath(); ?>"></script>

    <!-- SPPLive: LiveComponent reactivity engine (wire:click, wire:model) -->
    <script src="<?php echo \SPP\App::getAssetUrl('core', 'admin_js', 'spplive.min.js'); ?>"></script>
</body>
</html>