<?php
namespace SPPMod\SPPIntegrations;

/**
 * Interface ContentProviderInterface
 * 
 * Defines the capability contract for drivers that handle CMS/Blogging entities.
 */
interface ContentProviderInterface
{
    /**
     * Pushes a standardized SPP Post into the guest application.
     */
    public function syncPost(array $sppPostData): bool;

    /**
     * Fetches a list of posts natively from the guest application.
     */
    public function fetchPosts(): array;
}
