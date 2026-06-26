<?php
namespace SPPMod\SPPAPI\Subscribers;

use SPP\Event\SubscriberInterface;
use SPP\Event\Event;

class ApiErrorSubscriber
{
    public static function getSubscribedEvents(): array
    {
        return [
            'core.error.exception' => 'onException'
        ];
    }

    public function onException(Event $event): void
    {
        // Only handle errors if the request is destined for the API
        // This prevents the API module from hijacking global UI errors
        if (!self::isApiRequest()) {
            return;
        }

        $payload = $event->getPayload();
        $exception = $payload['exception'] ?? null;

        $message = $exception ? $exception->getMessage() : 'An unexpected error occurred.';
        $code = $exception ? $exception->getCode() : 500;

        if ($code < 400 || $code > 599) {
            $code = 500;
        }

        // Output JSON representation of the error
        \SPPMod\SPPAPI\SPPAjax::respond('error', ['message' => $message], $code);

        // Stop event propagation since we've handled the error and sent the response
        $event->stopPropagation();
    }

    private static function isApiRequest(): bool
    {
        // Check if we are inside the API routing context
        if (isset($_SERVER['HTTP_X_SPP_AJAX']) && $_SERVER['HTTP_X_SPP_AJAX'] === '1') {
            return true;
        }
        if (isset($_SERVER['X-SPP-Ajax']) && $_SERVER['X-SPP-Ajax'] === '1') {
            return true;
        }
        if (isset($_REQUEST['__api']) || isset($_REQUEST['__svc']) || isset($_REQUEST['__spa'])) {
            return true;
        }
        // Fallback checks (e.g. URI matching /api.php)
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/api.php') !== false || strpos($uri, '/api/') !== false) {
            return true;
        }

        return false;
    }
}
