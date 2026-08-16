<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * CachePurgeCommand
 * 
 * Sends PURGE or BAN requests to a configured Varnish reverse proxy or CDN.
 */
class CachePurgeCommand extends Command
{
    public function getName(): string
    {
        return 'cache:purge';
    }

    public function getDescription(): string
    {
        return 'Purge cache tags or URLs from the reverse proxy (Varnish/CDN).';
    }

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $tags = [];
        $url = null;

        $tagsOpt = $this->getOption($args, 'tags');
        if ($tagsOpt) {
            $tags = explode(',', $tagsOpt);
        }
        
        $url = $this->getOption($args, 'url');

        if (empty($tags) && !$url) {
            $this->error("Usage: php spp.php cache:purge [--tags=tag1,tag2] [--url=/path]");
            return;
        }

        // Get Varnish endpoint from config
        $proxyUrl = 'http://127.0.0.1:80'; // Default Varnish IP
        if (class_exists('\\SPP\\SPPConfig')) {
            $proxyUrl = \SPP\SPPConfig::get('reverse_proxy_url') ?: $proxyUrl;
        }

        if ($url) {
            $this->purgeUrl($proxyUrl, $url);
        }

        if (!empty($tags)) {
            $this->banTags($proxyUrl, $tags);
        }
    }

    private function purgeUrl(string $proxyUrl, string $url): void
    {
        $target = rtrim($proxyUrl, '/') . '/' . ltrim($url, '/');
        $this->info("Sending PURGE to: {$target}");
        
        $ch = curl_init($target);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PURGE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->line("Result: HTTP {$code}");
    }

    private function banTags(string $proxyUrl, array $tags): void
    {
        $target = rtrim($proxyUrl, '/');
        $tagStr = implode(',', $tags);
        $this->info("Sending BAN for tags: {$tagStr} to {$target}");
        
        $ch = curl_init($target);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "BAN");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-Cache-Tags: {$tagStr}"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->line("Result: HTTP {$code}");
    }
}
