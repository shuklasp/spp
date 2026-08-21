<?php
/**
 * ============================================================================
 * Custom 404 — Page Not Found
 * ============================================================================
 *
 * HOW SPP ERROR HANDLING WORKS:
 *
 * 1. BOOTSTRAP SETUP:
 *    SPPErrorHandler::register() is called during bootstrap to set up PHP's
 *    error and exception handlers. This catches all uncaught errors globally.
 *
 * 2. EXCEPTION HANDLING:
 *    SPPError::exceptionHandler() processes all uncaught exceptions.
 *    In debug mode (SPP_DEBUG=true), it shows Ignition-style error pages
 *    with stack traces and code context. In production, it shows user-friendly pages.
 *
 * 3. CUSTOM HANDLERS:
 *    You can override the default error display:
 *      SPPError::setCustomErrorHandler(function($error) {
 *          // Your custom error display logic
 *      });
 *
 * 4. ERROR TYPES:
 *    SPPError::triggerUserError('msg')   — Displayed to end users
 *    SPPError::triggerDevError('msg')    — Displayed only in debug mode
 *    SPPError::triggerAdminError('msg')  — Logged and sent to admin
 *
 * 5. THE 'PageNotFound' EVENT:
 *    When no route matches, SPP fires the 'PageNotFound' event.
 *    Listen to it in init.php to redirect here:
 *
 *      \SPP\SPPEvent::listen('PageNotFound', function($params) {
 *          http_response_code(404);
 *          include __DIR__ . '/pages/errors/404.php';
 *          exit;
 *      });
 *
 * 6. THE 'core.error.exception' EVENT:
 *    Fired when any uncaught exception occurs. Use for global logging:
 *
 *      \SPP\SPPEvent::listen('core.error.exception', function($params) {
 *          \SPP\Log::error('Exception: ' . $params->get('message'));
 *      });
 *
 * 7. API ERRORS:
 *    When the URL starts with /api/, SPP automatically returns JSON errors:
 *    {"error": "Not Found", "status": 404}
 *
 * 8. DEBUG vs PRODUCTION:
 *    SPP_DEBUG=true  → Ignition-style error page with stack trace
 *    SPP_DEBUG=false → This user-friendly page is shown instead
 *
 * ============================================================================
 */

http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Page Not Found</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            box-shadow: 0 25px 60px rgba(0,0,0,0.15);
        }
        .error-code {
            font-size: 7rem;
            font-weight: 800;
            background: linear-gradient(135deg, #6366f1, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
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
            background: #6366f1;
            color: #fff;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .error-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(99,102,241,0.4);
        }
        .error-path {
            margin-top: 1.5rem;
            font-size: 0.8rem;
            color: #94a3b8;
        }
        .error-path code {
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-code">404</div>
        <h1 class="error-title">Page Not Found</h1>
        <p class="error-message">
            The page you are looking for does not exist or has been moved.
            Check the URL or navigate back to the home page.
        </p>
        <a href="/" class="error-btn">Go Home</a>
        <p class="error-path">
            Requested: <code><?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/'); ?></code>
        </p>
    </div>
</body>
</html>