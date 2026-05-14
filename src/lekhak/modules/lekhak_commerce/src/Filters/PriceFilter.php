<?php
namespace SPPMod\LekhakCommerce\Filters;

use SPPMod\Lekhak\Core\FilterInterface;

/**
 * Class PriceFilter
 * Injects commerce data (prices, buy buttons) into Lekhak nodes.
 */
class PriceFilter implements FilterInterface
{
    public function getPriority(): int
    {
        return 60; // Run after content filters
    }

    public function preProcess(string &$content, array &$context): void
    {
        // Add price to context if it's a product
        $node = $context['data']['node'] ?? null;
        if ($node && $node->get('price')) {
            $context['data']['formatted_price'] = '$' . number_decode($node->get('price'));
        }
    }

    public function postProcess(string &$output, array &$context): void
    {
        $node = $context['data']['node'] ?? null;
        if (!$node || !$node->get('price')) return;

        $price = '$' . number_format($node->get('price'), 2);
        $btn = "
            <div class='lekhak-commerce-buy'>
                <span class='price'>$price</span>
                <button class='spp-btn spp-btn-success' onclick='LekhakCart.add({$node->getId()})'>Add to Cart</button>
            </div>
        ";

        // Inject into the 'after_content' slot if it exists
        if (strpos($output, '<!-- lekhak-slot:after_content -->') !== false) {
            $output = str_replace('<!-- lekhak-slot:after_content -->', $btn, $output);
        } else {
            // Fallback: Append to body
            $output = str_replace('</body>', $btn . '</body>', $output);
        }
    }
}
