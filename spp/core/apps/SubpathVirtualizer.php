<?php
declare(strict_types=1);

namespace SPP\Core\Apps;

/**
 * Class SubpathVirtualizer
 *
 * Provides bidirectional request and response virtualization for applications
 * mounted inside route directories (e.g. /blog, /ai, /chat) or on subdomains.
 *
 * Guarantees that:
 * 1. The guest application perceives itself as running on a native root document root.
 * 2. Inbound HTTP headers inject ambient SPP context (X-Forwarded-Prefix, authenticated user claims).
 * 3. Outbound HTTP redirects (Location: /login) are automatically prefixed with the route directory.
 * 4. Cookies are strictly scoped to the application subpath.
 * 5. HTML responses have <base href="/route/"> automatically injected to prevent 404 broken assets.
 *
 * @package SPP\Core\Apps
 * @author Satya Prakash Shukla
 */
class SubpathVirtualizer
{
    /**
     * Builds headers to forward to the upstream application daemon or FPM pool.
     *
     * @param array<string, mixed> $app App configuration from ForeignAppRegistry
     * @param string $matchedRoute e.g. '/blog' or '/ai'
     * @param string $matchType 'route' | 'subdomain' | 'domain'
     * @param string|null $appToken Ephemeral secret token for signing claims
     * @return array<string, string>
     */
    public static function buildIngressHeaders(
        array $app,
        string $matchedRoute,
        string $matchType = 'route',
        ?string $appToken = null
    ): array {
        $prefix = ($matchType === 'route') ? rtrim($matchedRoute, '/') : '';
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443 ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
        $clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $headers = [
            'X-Forwarded-Prefix' => $prefix !== '' ? $prefix : '/',
            'X-Forwarded-Proto'  => $proto,
            'X-Forwarded-Host'   => $host,
            'X-Forwarded-For'    => $clientIp,
            'X-SPP-Gateway'      => '2.0',
            'X-SPP-App-Id'       => (string)($app['id'] ?? 'unknown'),
        ];

        // Ambient SSO Claim Injection
        if (!empty($app['spp_integration']['sso_bridge']) || !empty($app['spp_integration']['ambient_sensing'])) {
            $userClaims = self::resolveCurrentUserClaims();
            if ($userClaims !== null) {
                $signingSecret = trim((string)($appToken ?: ($app['spp_integration']['signing_secret'] ?? ($app['secret'] ?? ''))));
                if ($signingSecret === '' || $signingSecret === 'spp_default_secret_key') {
                    throw new \RuntimeException("SSO Bridge Configuration Error: Application '{$app['id']}' does not have a configured signing secret. Refusing to sign identity claims.");
                }

                $timestamp = (string)time();
                $nonce = bin2hex(random_bytes(16));
                $signaturePayload = $userClaims['id'] . ':' . $userClaims['email'] . ':' . $timestamp . ':' . $nonce;
                $signature = hash_hmac('sha256', $signaturePayload, $signingSecret);

                $headers['X-SPP-User-Id']    = (string)$userClaims['id'];
                $headers['X-SPP-User-Email'] = (string)$userClaims['email'];
                $headers['X-SPP-User-Roles'] = implode(',', $userClaims['roles'] ?? []);
                $headers['X-SPP-Timestamp']  = $timestamp;
                $headers['X-SPP-Nonce']      = $nonce;
                $headers['X-SPP-Signature']  = $signature;
            }
        }

        return $headers;
    }

    /**
     * Atomically records and consumes an SSO nonce to prevent replay attacks.
     *
     * @param string $nonce
     * @param int $timestamp
     * @param int $maxAgeSeconds
     * @return bool True if nonce was successfully consumed (first use), false if replayed or invalid
     */
    public static function consumeNonce(string $nonce, int $timestamp, int $maxAgeSeconds = 300): bool
    {
        $cleanNonce = preg_replace('/[^a-zA-Z0-9_\-]/', '', $nonce);
        if ($cleanNonce === '' || strlen($cleanNonce) < 8) {
            return false;
        }

        // Try APCu first if available
        if (function_exists('apcu_add')) {
            $cacheKey = 'spp_sso_nonce_' . $cleanNonce;
            return apcu_add($cacheKey, 1, $maxAgeSeconds);
        }

        // Persistent atomic SQLite ledger
        try {
            $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2);
            $varDir = $baseDir . '/var/shared';
            if (!is_dir($varDir)) {
                @mkdir($varDir, 0777, true);
            }
            $dbFile = $varDir . '/sso_nonce_ledger.sqlite';
            $pdo = new \PDO('sqlite:' . $dbFile);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->exec('CREATE TABLE IF NOT EXISTS consumed_nonces (
                nonce TEXT PRIMARY KEY,
                expires_at INTEGER NOT NULL
            )');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_nonces_exp ON consumed_nonces (expires_at)');

            $now = time();
            // Clean up expired nonces
            $delStmt = $pdo->prepare('DELETE FROM consumed_nonces WHERE expires_at < ?');
            $delStmt->execute([$now]);

            // Attempt atomic insert
            $expiresAt = $now + $maxAgeSeconds;
            $insStmt = $pdo->prepare('INSERT INTO consumed_nonces (nonce, expires_at) VALUES (?, ?)');
            $insStmt->execute([$cleanNonce, $expiresAt]);
            return true;
        } catch (\Throwable $e) {
            // UNIQUE constraint violation indicates replay
            return false;
        }
    }

    /**
     * Verifies HMAC SSO claims with expiry window, replay defense, and timing attack protection.
     *
     * @param string|int $userId
     * @param string $email
     * @param string|int $timestamp
     * @param string $signature
     * @param string $token
     * @param int $maxAgeSeconds Maximum age of claim in seconds to prevent replay attacks (default: 300)
     * @param string|null $nonce Unique nonce (jti) for atomic one-time claim verification
     * @return bool
     */
    public static function verifySsoSignature(
        string|int $userId,
        string $email,
        string|int $timestamp,
        string $signature,
        string $token,
        int $maxAgeSeconds = 300,
        ?string $nonce = null
    ): bool {
        $cleanToken = trim($token);
        if ($cleanToken === '' || $cleanToken === 'spp_default_secret_key') {
            return false;
        }

        $ts = (int)$timestamp;
        $now = time();

        // Enforce strict monotonic timestamp checking (reject timestamps > 15s in future or older than window)
        if ($ts > ($now + 15) || ($now - $ts) > $maxAgeSeconds || ($now - $ts) < -15) {
            return false;
        }

        // If nonce is provided, enforce atomic check-and-set replay protection
        if ($nonce !== null && $nonce !== '') {
            if (!self::consumeNonce($nonce, $ts, $maxAgeSeconds)) {
                return false; // Replay attack detected!
            }
            $expectedPayload = $userId . ':' . $email . ':' . $timestamp . ':' . $nonce;
            $expectedSignature = hash_hmac('sha256', $expectedPayload, $cleanToken);
            return hash_equals($expectedSignature, $signature);
        }

        // Backward compatibility for legacy signatures without nonce
        $expectedLegacy = $userId . ':' . $email . ':' . $timestamp;
        $expectedSigLegacy = hash_hmac('sha256', $expectedLegacy, $cleanToken);

        return hash_equals($expectedSigLegacy, $signature);
    }

    /**
     * Resolves authenticated SPP user claims if active in session.
     *
     * @return array{id: string|int, email: string, roles: array<string>}|null
     */
    public static function resolveCurrentUserClaims(): ?array
    {
        if (!empty($_SESSION['spp_user'])) {
            $u = $_SESSION['spp_user'];
            return [
                'id'    => $u['id'] ?? $u['user_id'] ?? 1,
                'email' => $u['email'] ?? 'user@spp.internal',
                'roles' => (array)($u['roles'] ?? ['user']),
            ];
        }

        // Check if class-based Auth is available
        if (class_exists('\\SPPMod\\SPPAuth\\SPPAuth')) {
            try {
                if (\SPPMod\SPPAuth\SPPAuth::check()) {
                    $u = \SPPMod\SPPAuth\SPPAuth::user();
                    if ($u) {
                        return [
                            'id'    => method_exists($u, 'getId') ? $u->getId() : ($u->id ?? 1),
                            'email' => method_exists($u, 'getEmail') ? $u->getEmail() : ($u->email ?? 'admin@spp.internal'),
                            'roles' => method_exists($u, 'getRoles') ? $u->getRoles() : ['admin'],
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // Ignore auth inspection exceptions in headless contexts
            }
        }

        return null;
    }

    /**
     * Rewrites upstream redirect Location header to guarantee route directory isolation.
     *
     * @param string $location Location header value sent by upstream app
     * @param string $routePrefix e.g. '/blog' or '/ai'
     * @param string $matchType 'route' | 'subdomain' | 'domain'
     * @return string
     */
    public static function rewriteRedirect(string $location, string $routePrefix, string $matchType = 'route'): string
    {
        if ($matchType !== 'route' || $routePrefix === '/' || $routePrefix === '') {
            return $location;
        }

        $cleanPrefix = '/' . trim($routePrefix, '/');

        // Relative path redirect (e.g. '/login', '/dashboard/settings')
        if (str_starts_with($location, '/')) {
            if ($location === $cleanPrefix || str_starts_with($location, $cleanPrefix . '/')) {
                return $location; // Already prefixed
            }
            return $cleanPrefix . $location;
        }

        // Full URL redirect (e.g. 'http://localhost:10002/login')
        $parts = parse_url($location);
        if (isset($parts['path'])) {
            $path = $parts['path'];
            if ($path !== $cleanPrefix && !str_starts_with($path, $cleanPrefix . '/')) {
                $newPath = $cleanPrefix . ($path === '/' ? '' : $path);
                $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
                $host = $parts['host'] ?? '';
                $port = isset($parts['port']) ? ':' . $parts['port'] : '';
                $query = isset($parts['query']) ? '?' . $parts['query'] : '';
                $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

                // If host is the internal loopback upstream host, swap with current public host
                $pubHost = $_SERVER['HTTP_HOST'] ?? $host;
                return $scheme . $pubHost . $newPath . $query . $fragment;
            }
        }

        return $location;
    }

    /**
     * Scopes Set-Cookie header to application subpath route.
     *
     * @param string $cookieHeader
     * @param string $routePrefix
     * @param string $matchType
     * @return string
     */
    public static function rewriteCookie(string $cookieHeader, string $routePrefix, string $matchType = 'route'): string
    {
        if ($matchType !== 'route' || $routePrefix === '/' || $routePrefix === '') {
            return $cookieHeader;
        }

        $cleanPrefix = '/' . trim($routePrefix, '/');

        // Check if cookie has Path=/
        if (preg_match('/;\s*Path=\/(\s*;|$)/i', $cookieHeader)) {
            return preg_replace('/;\s*Path=\/(\s*;|$)/i', '; Path=' . $cleanPrefix . '/$1', $cookieHeader);
        }

        // If no Path directive present, append it
        if (!preg_match('/;\s*Path=/i', $cookieHeader)) {
            return rtrim($cookieHeader, '; ') . '; Path=' . $cleanPrefix . '/';
        }

        return $cookieHeader;
    }

    /**
     * Injects <base href="/route/"> into HTML <head> to ensure assets and relative links work natively.
     *
     * @param string $html
     * @param string $routePrefix
     * @param string $matchType
     * @return string
     */
    public static function rewriteHtmlBody(string $html, string $routePrefix, string $matchType = 'route'): string
    {
        if ($matchType !== 'route' || $routePrefix === '/' || $routePrefix === '') {
            return $html;
        }

        // Do not inject if <base is already declared
        if (stripos($html, '<base ') !== false) {
            return $html;
        }

        $cleanPrefix = '/' . trim($routePrefix, '/');
        $baseTag = "\n    <base href=\"{$cleanPrefix}/\">\n";

        // Inject right after opening <head> or <head ...>
        if (preg_match('/<head(\s[^>]*)?>/i', $html, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1] + strlen($matches[0][0]);
            return substr($html, 0, $pos) . $baseTag . substr($html, $pos);
        }

        return $html;
    }
}
