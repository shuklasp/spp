<?php

namespace App\SPPDocs\Services;

/**
 * SSRFGuard
 * Protects internal infrastructure against Server-Side Request Forgery (SSRF)
 * when dispatching webhooks or fetching remote assets.
 */
class SSRFGuard
{
    /**
     * Asserts that a URL is safe for outbound HTTP requests from the server.
     * Throws an \InvalidArgumentException if the URL resolves to a private, loopback, or reserved IP.
     *
     * @param string $url The target URL to validate
     * @param bool $allowIntranet Whether to allow private intranet IPs (if explicitly configured)
     * @return string The validated URL
     * @throws \InvalidArgumentException
     */
    public static function assertSafeUrl(string $url, bool $allowIntranet = false): string
    {
        $url = trim($url);
        if (empty($url)) {
            throw new \InvalidArgumentException('URL cannot be empty.');
        }

        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            throw new \InvalidArgumentException('Invalid URL structure.');
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException("Unsupported URL scheme '{$scheme}'. Only http and https are permitted.");
        }

        $host = $parts['host'];

        // Block localhost and standard loopback hostnames
        if (in_array(strtolower($host), ['localhost', 'localhost.localdomain', 'ip6-localhost', 'ip6-loopback'], true)) {
            if (!$allowIntranet) {
                throw new \InvalidArgumentException("SSRF blocked: Requests to '{$host}' are forbidden.");
            }
        }

        // If host is an IP address
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (!$allowIntranet && self::isPrivateOrReservedIp($host)) {
                throw new \InvalidArgumentException("SSRF blocked: Direct requests to private or reserved IP '{$host}' are forbidden.");
            }
            return $url;
        }

        // Resolve DNS records to verify resolved IPs
        $ips = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (empty($ips)) {
            // Fallback to gethostbynamel
            $hostIps = @gethostbynamel($host);
            if (!empty($hostIps)) {
                foreach ($hostIps as $ip) {
                    if (!$allowIntranet && self::isPrivateOrReservedIp($ip)) {
                        throw new \InvalidArgumentException("SSRF blocked: Host '{$host}' resolves to private IP '{$ip}'.");
                    }
                }
            }
            return $url;
        }

        foreach ($ips as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if ($ip && !$allowIntranet && self::isPrivateOrReservedIp($ip)) {
                throw new \InvalidArgumentException("SSRF blocked: Host '{$host}' resolves to private/reserved IP '{$ip}'.");
            }
        }

        return $url;
    }

    /**
     * Checks whether an IP address belongs to RFC 1918, loopback, link-local, or cloud metadata.
     */
    public static function isPrivateOrReservedIp(string $ip): bool
    {
        // Check IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // Check flags for private and reserved ranges
            $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
            if (filter_var($ip, FILTER_VALIDATE_IP, $flags) === false) {
                return true;
            }

            // Cloud metadata IP: 169.254.169.254 (link-local)
            if (str_starts_with($ip, '169.254.')) {
                return true;
            }

            // Loopback 127.0.0.0/8
            if (str_starts_with($ip, '127.')) {
                return true;
            }

            // 0.0.0.0/8
            if (str_starts_with($ip, '0.')) {
                return true;
            }

            return false;
        }

        // Check IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
            if (filter_var($ip, FILTER_VALIDATE_IP, $flags) === false) {
                return true;
            }

            // IPv6 loopback ::1
            if ($ip === '::1') {
                return true;
            }

            // IPv4-mapped IPv6 (::ffff:127.0.0.1)
            if (str_starts_with(strtolower($ip), '::ffff:')) {
                $mappedIpv4 = substr($ip, 7);
                return self::isPrivateOrReservedIp($mappedIpv4);
            }

            return false;
        }

        return true;
    }
}
