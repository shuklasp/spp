<?php
/**
 * ============================================================================
 * Application Boot — SPPDocs
 * ============================================================================
 *
 * HOW THIS WORKS:
 * This file is loaded automatically by the SPP framework during bootstrap
 * when the 'SPPDocs' application context is active. It runs ONCE per request.
 *
 * USE THIS FOR:
 *   - Registering autoloaders for app-specific namespaces
 *   - Registering event listeners
 *   - Setting up custom middleware
 *   - Configuring services
 *
 * DO NOT USE FOR:
 *   - Output (echo/print) — this runs before any rendering
 *   - Heavy processing — keep boot fast
 * ============================================================================
 */

// ── App-Specific Autoloader ─────────────────────────────────────────────
// Maps the \App\SPPDocs\ namespace to this app's directory.
// This allows you to use classes like \App\SPPDocs\Serv\HomeController
spl_autoload_register(function ($className) {
    // Handle App\SPPDocs\... and SPPDocs\... namespaces
    $prefix = 'App\\SPPDocs\\';
    $altPrefix = 'SPPDocs\\';
    if (str_starts_with($className, $prefix)) {
        $relative = substr($className, strlen($prefix));
    } elseif (str_starts_with($className, $altPrefix)) {
        $relative = substr($className, strlen($altPrefix));
    } else {
        return;
    }
    $parts = explode('\\', $relative);

    $loadFile = function ($filePath) use ($relative) {
        if (file_exists($filePath)) {
            require_once $filePath;
            $classA = 'App\\SPPDocs\\' . $relative;
            $classB = 'SPPDocs\\' . $relative;
            if (class_exists($classA, false) && !class_exists($classB, false)) {
                class_alias($classA, $classB);
            } elseif (class_exists($classB, false) && !class_exists($classA, false)) {
                class_alias($classB, $classA);
            }
            return true;
        }
        return false;
    };

    $aliasAll = function ($serviceName) {
        $clsA = 'App\\SPPDocs\\Services\\' . $serviceName;
        $clsPkg = 'App\\SPPDocs\\Packages\\Erp\\Services\\' . $serviceName;
        $clsB = 'SPPDocs\\Services\\' . $serviceName;
        $clsPkgB = 'SPPDocs\\Packages\\Erp\\Services\\' . $serviceName;

        $definedClass = null;
        if (class_exists($clsA, false)) $definedClass = $clsA;
        elseif (class_exists($clsPkg, false)) $definedClass = $clsPkg;
        elseif (class_exists($clsB, false)) $definedClass = $clsB;
        elseif (class_exists($clsPkgB, false)) $definedClass = $clsPkgB;

        if ($definedClass) {
            foreach ([$clsA, $clsPkg, $clsB, $clsPkgB] as $alias) {
                if ($alias !== $definedClass && !class_exists($alias, false)) {
                    class_alias($definedClass, $alias);
                }
            }
        }
    };

    // 1. Direct path for Core
    if (strcasecmp($parts[0], 'Core') === 0) {
        $file = __DIR__ . '/Core/' . implode('/', array_slice($parts, 1)) . '.php';
        if ($loadFile($file)) return;
    }

    // 2. Direct path for Packages (e.g. Packages\Erp\Services\... -> packages/erp/Services/...)
    if (strcasecmp($parts[0], 'Packages') === 0 && count($parts) > 2) {
        $pkgName = strtolower($parts[1]);
        $subParts = array_slice($parts, 2);
        $file = __DIR__ . '/packages/' . $pkgName . '/' . implode('/', $subParts) . '.php';
        if (file_exists($file)) {
            require_once $file;
            if ($pkgName === 'erp' && strcasecmp($subParts[0], 'Services') === 0) {
                $serviceName = implode('\\', array_slice($subParts, 1));
                $aliasAll($serviceName);
            } else {
                $classA = 'App\\SPPDocs\\' . $relative;
                $classB = 'SPPDocs\\' . $relative;
                if (class_exists($classA, false) && !class_exists($classB, false)) {
                    class_alias($classA, $classB);
                } elseif (class_exists($classB, false) && !class_exists($classA, false)) {
                    class_alias($classB, $classA);
                }
            }
            return;
        }
    }

    // 3. Exact PSR-4 case directory mapping (Controllers, Services, Entities, Classes, etc.)
    $fileExact = __DIR__ . '/' . implode('/', $parts) . '.php';
    if ($loadFile($fileExact)) return;

    // 3.5. Dynamic fallback for package services (Services\... -> packages/*/Services/...)
    if (strcasecmp($parts[0], 'Services') === 0 && count($parts) > 1) {
        $serviceRel = implode('/', array_slice($parts, 1)) . '.php';
        $packagesDir = __DIR__ . '/packages';
        if (is_dir($packagesDir)) {
            foreach (glob($packagesDir . '/*', GLOB_ONLYDIR) as $pkgDir) {
                $pkgServiceFile = $pkgDir . '/Services/' . $serviceRel;
                if (file_exists($pkgServiceFile)) {
                    require_once $pkgServiceFile;
                    $serviceName = implode('\\', array_slice($parts, 1));
                    $aliasAll($serviceName);
                    return;
                }
            }
        }
    }

    // 4. Fallback lowercase first-directory mapping (Serv -> serv, etc.)
    $fileLower = __DIR__ . '/' . strtolower($parts[0]);
    if (count($parts) > 1) {
        $fileLower .= '/' . implode('/', array_slice($parts, 1));
    }
    $fileLower .= '.php';
    if ($loadFile($fileLower)) return;
});

// ── Autonomous Sub-Package Namespace Autoloader (Lekhak, Samvaad) ───────
spl_autoload_register(function ($className) {
    if (str_starts_with($className, 'App\\Lekhak\\') || str_starts_with($className, 'Lekhak\\')) {
        $prefix = str_starts_with($className, 'App\\Lekhak\\') ? 'App\\Lekhak\\' : 'Lekhak\\';
        $relative = substr($className, strlen($prefix));
        $parts = explode('\\', $relative);
        $file = __DIR__ . '/packages/cms/' . implode('/', $parts) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
        $fileLower = __DIR__ . '/packages/cms/' . strtolower($parts[0]);
        if (count($parts) > 1) {
            $fileLower .= '/' . implode('/', array_slice($parts, 1));
        }
        $fileLower .= '.php';
        if (file_exists($fileLower)) {
            require_once $fileLower;
            return;
        }
        // Fallback to packages/lekhak if present
        $fallback = __DIR__ . '/packages/lekhak/' . implode('/', $parts) . '.php';
        if (file_exists($fallback)) {
            require_once $fallback;
            return;
        }
    }

    if (str_starts_with($className, 'App\\Samvaad\\') || str_starts_with($className, 'Samvaad\\')) {
        $prefix = str_starts_with($className, 'App\\Samvaad\\') ? 'App\\Samvaad\\' : 'Samvaad\\';
        $relative = substr($className, strlen($prefix));
        $parts = explode('\\', $relative);
        $file = __DIR__ . '/packages/social/' . implode('/', $parts) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
        $fileLower = __DIR__ . '/packages/social/' . strtolower($parts[0]);
        if (count($parts) > 1) {
            $fileLower .= '/' . implode('/', array_slice($parts, 1));
        }
        $fileLower .= '.php';
        if (file_exists($fileLower)) {
            require_once $fileLower;
            return;
        }
    }
});

// ── Autonomous Package Kernel Boot ──────────────────────────────────────
if (class_exists('\\App\\SPPDocs\\Core\\PackageKernel')) {
    \App\SPPDocs\Core\PackageKernel::boot();
}

// ── Event Registration ──────────────────────────────────────────────────
// Register event listeners for framework lifecycle events.
// Uncomment to activate:
//
// \SPP\SPPEvent::listen('PageNotFound', function ($params) {
//     // Custom 404 handling
//     echo "<h1>Page not found in SPPDocs</h1>";
// });
//
// \SPP\SPPEvent::listen('app.boot', function ($params) {
//     // Runs when app context is set
// });

// ── SPP-UX Auto-Boot ────────────────────────────────────────────────────
// Automatically register SPP-UX assets for all pages in this app.
// This means @sppux directives and SPPUX::render() work on any page.
if (php_sapi_name() !== 'cli' && class_exists('\\SPPMod\\Drishyam\\SPPUX')) {
    \SPPMod\Drishyam\SPPUX::boot('SPPDocs');
}

// ── Dynamic Asset Inclusion ───────────────────────────────────
// Automatically injects mapped CSS and JS assets from module.yml
// configurations via the secure AssetRouter alias system.
if (php_sapi_name() !== 'cli') {
    \SPP\App::includeAssets();
}