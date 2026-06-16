<?php

namespace SPP;

/**
 * class \SPP\Scheduler
 *
 * Handles context and process scheduling for SPP.
 *
 * @author
 *     Satya Prakash Shukla
 * @version
 *     2.1 compatible with legacy SPP 1.x
 */
class Scheduler extends \SPP\SPPObject
{
    /** @var string */
    private static string $AppContext = '';

    /** @var array<string,\SPP\App> */
    private static array $procs = [];

    /**
     * Set or switch application context.
     *
     * @throws \SPP\SPPException
     */
    public static function setContext(string $context): void
    {
        $oldContext = self::$AppContext;
        $context = trim($context);
        $context = ($context === '') ? 'default' : $context;

        if (!array_key_exists($context, self::$procs)) {
            throw new \SPP\SPPException('Unregistered context: ' . $context);
        }

        if (self::$AppContext === '') {
            self::$AppContext = $context;
            return;
        }

        $currProc = self::getActiveProc();
        $newProc = self::getProcObj($context);

        $currProc->setStatus(\SPP\App::APP_WAITING);
        $newProc->setStatus(\SPP\App::APP_EXEC);

        self::$AppContext = $context;

        // Trace Logging
        if (defined('SPP_DEBUG') && SPP_DEBUG) {
            $logMsg = date('[Y-m-d H:i:s]') . " Context Switch: {$oldContext} -> {$context}\n";
            @file_put_contents(SPP_LOG_DIR . '/spp_context.log', $logMsg, FILE_APPEND);
        }
    }

    /**
     * Register a new \SPP\App process.
     *
     * @throws \SPP\SPPException
     */
    public static function regProc(\SPP\App $proc): void
    {
        $pname = $proc->getName();

        if (!isset(self::$procs[$pname])) {
            self::$procs[$pname] = $proc;
        }
    }

    /**
     * Get current active context name.
     */
    public static function getContext(): string
    {
        return self::$AppContext;
    }

    /**
     * Check if an application context has been set.
     */
    public static function hasContext(): bool
    {
        return self::$AppContext !== '';
    }

    /**
     * Get module configuration directory for current process.
     */
    public static function getModsConfDir(): string
    {
        return self::getActiveProc()->getModsConfDir();
    }

    /**
     * Get \SPP\App object for a specific process.
     *
     * @throws \SPP\SPPException
     */
    public static function getProcObj(string $pname): \SPP\App
    {
        if (!array_key_exists($pname, self::$procs)) {
            throw new \SPP\SPPException('Unregistered process: ' . $pname);
        }

        return self::$procs[$pname];
    }

    /**
     * Get currently active \SPP\App process.
     *
     * @throws \SPP\SPPException
     */
    public static function getActiveProc(): \SPP\App
    {
        if (self::$AppContext === '') {
            throw new \SPP\SPPException('Application context not set.');
        }

        return self::$procs[self::$AppContext];
    }

    /**
     * Get active SPPError object from the current app.
     */
    public static function getActiveErrorObj(): ?\SPP\SPPError
    {
        return self::getActiveProc()->getErrorObj();
    }

    /**
     * Detects the app context based on Request URI and base_url in global registry.
     * Enforces strict prefixing.
     */
    public static function detectAndEnforceContext(): void
    {
        \SPP\Registry::loadShared();
        \SPP\SPPEvent::registerEvent('event_spp_context_enforce');
        \SPP\SPPEvent::registerEvent('event_spp_route_resolve');

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = explode('?', $uri)[0];

        // Normalize URI if running in a subdirectory
        $root = str_replace('\\', '/', SPP_DOC_ROOT);
        $appBase = str_replace('\\', '/', SPP_APP_DIR);

        if ($root !== '') {
            $subDir = trim(str_replace($root, '', $appBase), '/');
            if ($subDir !== '') {
                $uri = '/' . ltrim(str_replace('/' . $subDir, '', $uri), '/');
            }
        }
        $uri = ($uri === '') ? '/' : $uri;

        $apps = \SPP\App::getGlobalSettings('apps') ?: [];

        $params = ['uri' => $uri, 'apps' => $apps, 'context' => null];
        $evtParams = new \SPP\EventParams($params);
        \SPP\SPPEvent::fireEvent('event_spp_context_enforce', $evtParams, function ($p) {
            $payload = $p->getPayload();
            foreach ($payload['apps'] as $name => $cfg) {
                $base = $cfg['base_url'] ?? '/' . $name;
                if ($payload['uri'] === $base || strpos($payload['uri'], $base . '/') === 0) {
                    $payload['context'] = $name;
                    $p->setPayload($payload);
                    return;
                }
            }
        });
        $params = $evtParams->getPayload();

        $matchedApp = $params['context'] ?: (\SPP\App::getGlobalSettings('base_app') ?: 'default');

        $routeParams = ['uri' => $uri, 'context' => $matchedApp];
        $routeEvtParams = new \SPP\EventParams($routeParams);
        \SPP\SPPEvent::fireEvent('event_spp_route_resolve', $routeEvtParams, function ($p) {
            // Default: do nothing, context already matched
        });
        $routeParams = $routeEvtParams->getPayload();
        $matchedApp = $routeParams['context'];

        self::$AppContext = $matchedApp;
    }

    /**
     * Executes a callback within a specific application context.
     * Safely switches and restores context.
     *
     * @param string $context
     * @param callable $callback
     * @return mixed
     */
    public static function withContext(string $context, callable $callback)
    {
        $oldContext = self::$AppContext;
        if ($oldContext === $context) {
            return $callback();
        }

        // Ensure the target app is initialized if it's not already
        if (!isset(self::$procs[$context])) {
            new \SPP\App($context, false, 3);
        }

        self::setContext($context);
        try {
            return $callback();
        } finally {
            if ($oldContext !== '') {
                self::setContext($oldContext);
            } else {
                self::$AppContext = '';
            }
        }
    }
}
