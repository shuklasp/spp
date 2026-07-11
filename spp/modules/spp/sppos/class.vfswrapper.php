<?php
namespace SPPMod\SPPOS;

/**
 * Class VfsStreamWrapper
 * 
 * Implements the PHP streamWrapper interface to intercept `spp://` calls.
 * This is the core of the WebOS Virtual File System.
 */
class VfsStreamWrapper
{
    private static VfsProviderInterface $provider;
    private $position = 0;
    private $data = '';

    public static function register(VfsProviderInterface $provider)
    {
        self::$provider = $provider;
        stream_wrapper_register('spp', __CLASS__);
    }

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $cleanPath = str_replace('spp://', '', $path);
        
        // INTERCEPT: Vault Secrets masking
        if (strpos($cleanPath, 'secrets/') === 0 && strpos($mode, 'r') !== false) {
            $appAlias = \SPPMod\SPPOS\KernelGuard::getCurrentAppId();
            $this->data = \SPPMod\SPPCrypto\Vault::synthesizeEnvFile($appAlias);
            return true;
        }

        if (strpos($mode, 'r') !== false) {
            if (!self::$provider->exists($cleanPath)) {
                return false;
            }
            // In a real OS, we would implement edge caching (Redis/Memcached) here!
            $this->data = self::$provider->read($cleanPath);
        }
        return true;
    }

    public function stream_read($count)
    {
        $ret = substr($this->data, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }

    public function stream_write($data)
    {
        $this->data .= $data;
        $this->position += strlen($data);
        return strlen($data);
    }

    public function stream_eof()
    {
        return $this->position >= strlen($this->data);
    }

    public function stream_close()
    {
        // On close, flush writes back to the provider (e.g. S3)
        // (Simplified for architectural demonstration)
    }
}
