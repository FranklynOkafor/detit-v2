<?php

namespace DetIt\WooCommerce;

use DetIt\Domain\ProductFactSheet;

if (! defined('ABSPATH')) {
    exit;
}

final class ProductFactSheetFactory
{
    public function create(
        ProductContext $context
    ): ProductFactSheet {

        return new ProductFactSheet(
            $context->id(),
            $context->title(),
            $context->sku(),
            $context->type(),
            $context->description(),
            $context->shortDescription(),
            $context->categories(),
            $context->tags(),
            $context->attributes(),
            $context->weight(),
            $context->dimensions()
        );
    }
}