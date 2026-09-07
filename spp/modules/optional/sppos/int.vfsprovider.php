<?php
namespace SPPMod\SPPOS;

/**
 * Interface VfsProviderInterface
 * 
 * Defines the contract for Virtual File System adapters.
 * Ensures the WebOS can swap between Local Disk, S3, GCS, etc.
 */
interface VfsProviderInterface
{
    public function read(string $path): string;
    public function write(string $path, string $content): bool;
    public function exists(string $path): bool;
}
