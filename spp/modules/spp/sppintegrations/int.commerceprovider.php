<?php
namespace SPPMod\SPPIntegrations;

/**
 * Interface CommerceProviderInterface
 * 
 * Defines the capability contract for drivers that handle eCommerce entities.
 * By implementing this, SPP knows the driver can handle Products and Orders.
 */
interface CommerceProviderInterface
{
    /**
     * Pushes a standardized SPP Product into the guest application.
     */
    public function syncProduct(array $sppProductData): bool;

    /**
     * Fetches a list of products natively from the guest application.
     */
    public function fetchProducts(): array;
    
    /**
     * Pushes a standardized SPP Order into the guest application.
     */
    public function syncOrder(array $sppOrderData): bool;
}
