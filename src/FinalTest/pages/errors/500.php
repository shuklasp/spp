<?php
/**
 * ============================================================================
 * Custom 500 — Internal Server Error
 * ============================================================================
 *
 * WHEN THIS PAGE IS SHOWN:
 *   - An uncaught exception occurs in PRODUCTION mode (SPP_DEBUG = false)
 *   - You manually include this page in your exception handler
 *
 * DEBUG vs PRODUCTION:
 *   SPP_DEBUG = true  → The framework shows an Ignition-style debug page
 *                        with full stack trace, code snippets, and context.
 *                        This file is NOT shown in debug mode.
 *
 *   SPP_DEBUG = false → This user-friendly page is shown instead.
 *                        The actual error is logged to var/log/ for developers.
 *
 * HOW TO CUSTOMIZE GLOBALLY:
 *   In your init.php, listen to the 'core.error.exception' event:
 *
 *     \SPP\SPPEvent::listen('core.error.exception', function($params) {
 *         if (!defined('SPP_DEBUG') || !SPP_DEBUG) {
 *             http_response_code(500);
 *             include __DIR__ . '/pages/errors/500.php';
 *             exit;
 *         }
 *     });
 *
 * LOGGING ERRORS:
 *   \SPP\Log::error('Message', ['exception' => $e->getMessage()]);
 *   Logs go to: var/log/app.log (auto-rotated by spplogger module)
 *
 * ============================================================================
 */

http_response_code(500);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 — Internal Server Error</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 50%, #991b1b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .error-card {
            background: #fff;
            border-radius: 24px;
            padding: 3rem;
            max-width: 580px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 60px rgba(0,0,0,0.2);
        }
        .error-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .error-code {
            font-size: 5rem;
            font-weight: 800;
            color: #ef4444;
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        .error-title {
            font-size: 1.5rem;
            color: #1e293b;
            margin-bottom: 1rem;
            font-weight: 700;
        }
        .error-message {
            color: #64748b;
            line-height: 1.7;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }
        .error-btn {
            display: inline-block;
            padding: 0.8rem 2rem;
            background: #ef4444;
            color: #fff;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .error-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239,68,68,0.4);
        }
        .error-hint {
            margin-top: 2rem;
            padding: 1rem;
            background: #fef2f2;
            border-radius: 10px;
            font-size: 0.82rem;
            color: #991b1b;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon">&#9888;&#65039;</div>
        <div class="error-code">500</div>
        <h1 class="error-title">Internal Server Error</h1>
        <p class="error-message">
            Something went wrong on our end. Our team has been notified
            and is working to fix the issue. Please try again later.
        </p>
        <a href="/" class="error-btn">Go Home</a>
        <div class="error-hint">
            <strong>Developer Tip:</strong> Check <code>var/log/</code> for detailed error logs.
            Enable <code>SPP_DEBUG=true</code> in development to see full stack traces.
        </div>
    </div>
</body>
</html>