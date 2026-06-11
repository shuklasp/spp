<?php

namespace SPP\Core;

use SPP\Core\Interfaces\SharedStorageInterface;

/**
 * Class FileSharedStorage
 * 
 * Local file-based shared storage adapter for the SPP Registry.
 * Uses atomic locking (flock) to prevent concurrency corruption.
 */
class FileSharedStorage implements SharedStorageInterface
{
    /** @var string */
    private string $filePath;

    public function __construct()
    {
        if (!defined('SPP_BASE_DIR')) {
            throw new \RuntimeException("SPP_BASE_DIR is not defined.");
        }
        $sharedDir = SPP_BASE_DIR . '/var/shared';
        if (!is_dir($sharedDir)) {
            @mkdir($sharedDir, 0777, true);
        }
        $this->filePath = $sharedDir . '/registry.json';
    }

    /**
     * @inheritDoc
     */
    public function save(array $data): void
    {
        $fp = @fopen($this->filePath, 'c+');
        if ($fp) {
            if (flock($fp, LOCK_EX)) {
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }
    }

    /**
     * @inheritDoc
     */
    public function load(): array
    {
        if (file_exists($this->filePath)) {
            $data = json_decode(file_get_contents($this->filePath), true);
            if (is_array($data)) {
                return $data;
            }
        }
        return [];
    }
}
