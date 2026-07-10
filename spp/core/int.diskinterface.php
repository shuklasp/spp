<?php

namespace SPP\Core;

/**
 * Interface DiskInterface
 */
interface DiskInterface
{
    public function get(string $path): ?string;
    public function put(string $path, string $contents): bool;
    public function exists(string $path): bool;
    public function delete(string $path): bool;
    public function readStream(string $path);
    public function writeStream(string $path, $resource): bool;
}
