<?php

namespace DetIt\WooCommerce;

if (! defined('ABSPATH')) {
    exit;
}

final class ProductReader
{
    public function read(int $productId): ?ProductContext
    {
        /*
         * Reject obviously invalid IDs before asking
         * WooCommerce to do any work.
         */
        if ($productId <= 0) {
            return null;
        }

        /*
         * DetIt requires WooCommerce, but keeping this
         * check here prevents an unexpected fatal error
         * if the function is somehow unavailable.
         */
        if (! function_exists('wc_get_product')) {
            return null;
        }

        /*
         * Ask WooCommerce for the correct product object.
         *
         * This may return a WC_Product object, false,
         * or null if the product cannot be loaded.
         */
        $product = wc_get_product($productId);

        if (! $product instanceof \WC_Product) {
            return null;
        }

        /*
         * Convert WooCommerce's product object into
         * DetIt's own stable ProductContext.
         *
         * More product fields will be added in Stage 23.
         */
        return new ProductContext(
            $product->get_id(),
            $product->get_name(),
            $product->get_type()
        );
    }
}