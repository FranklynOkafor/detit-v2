<?php

namespace DetIt\WooCommerce;

if (! defined('ABSPATH')) {
    exit;
}

final class ProductReader
{
    public function read(int $productId): ?ProductContext
    {
        if ($productId <= 0) {
            return null;
        }

        if (! function_exists('wc_get_product')) {
            return null;
        }

        $product = wc_get_product($productId);

        if (! $product instanceof \WC_Product) {
            return null;
        }

        return new ProductContext(
            (int) $product->get_id(),

            (string) $product->get_name(),

            (string) $product->get_sku(),

            (string) $product->get_type(),

            (string) $product->get_description(),

            (string) $product->get_short_description(),

            $this->readTerms(
                $product->get_category_ids(),
                'product_cat'
            ),

            $this->readTerms(
                $product->get_tag_ids(),
                'product_tag'
            ),

            $this->readAttributes($product),

            (string) $product->get_weight(),

            [
                'length' => (string) $product->get_length(),
                'width'  => (string) $product->get_width(),
                'height' => (string) $product->get_height(),
            ],

            $this->readImage(
                (int) $product->get_image_id()
            ),

            $this->readGalleryImages(
                $product->get_gallery_image_ids()
            )
        );
    }

    /**
     * Convert WooCommerce term IDs into stable,
     * plain DetIt data.
     *
     * @param array<int, int|string> $termIds
     * @return array<int, array{
     *     id:int,
     *     name:string,
     *     slug:string
     * }>
     */
    private function readTerms(
        array $termIds,
        string $taxonomy
    ): array {
        $terms = [];

        foreach ($termIds as $termId) {
            $term = get_term(
                (int) $termId,
                $taxonomy
            );

            if (! $term instanceof \WP_Term) {
                continue;
            }

            $terms[] = [
                'id'   => (int) $term->term_id,
                'name' => (string) $term->name,
                'slug' => (string) $term->slug,
            ];
        }

        return $terms;
    }

    /**
     * Convert WooCommerce attributes into
     * predictable DetIt arrays.
     *
     * @return array<int, array{
     *     name:string,
     *     label:string,
     *     options:array<int,string>,
     *     visible:bool,
     *     variation:bool
     * }>
     */
    private function readAttributes(
        \WC_Product $product
    ): array {
        $attributes = [];

        foreach ($product->get_attributes() as $attribute) {

            if (! $attribute instanceof \WC_Product_Attribute) {
                continue;
            }

            if ($attribute->is_taxonomy()) {

                $options = wc_get_product_terms(
                    $product->get_id(),
                    $attribute->get_name(),
                    [
                        'fields' => 'names',
                    ]
                );

                if (! is_array($options)) {
                    $options = [];
                }

            } else {

                $options = $attribute->get_options();
            }

            $options = array_map(
                'strval',
                $options
            );

            $attributes[] = [
                'name' => (string) $attribute->get_name(),

                'label' => (string) wc_attribute_label(
                    $attribute->get_name(),
                    $product
                ),

                'options' => array_values($options),

                'visible' => (bool) $attribute->get_visible(),

                'variation' => (bool) $attribute->get_variation(),
            ];
        }

        return $attributes;
    }

    /**
     * Convert one WordPress attachment into
     * predictable image data.
     *
     * @return array{id:int,url:string,alt:string}|null
     */
    private function readImage(
        int $imageId
    ): ?array {
        if ($imageId <= 0) {
            return null;
        }

        $url = wp_get_attachment_image_url(
            $imageId,
            'full'
        );

        if ($url === false) {
            $url = '';
        }

        return [
            'id' => $imageId,

            'url' => (string) $url,

            'alt' => (string) get_post_meta(
                $imageId,
                '_wp_attachment_image_alt',
                true
            ),
        ];
    }

    /**
     * @param array<int, int|string> $imageIds
     * @return array<int, array{
     *     id:int,
     *     url:string,
     *     alt:string
     * }>
     */
    private function readGalleryImages(
        array $imageIds
    ): array {
        $images = [];

        foreach ($imageIds as $imageId) {

            $image = $this->readImage(
                (int) $imageId
            );

            if ($image !== null) {
                $images[] = $image;
            }
        }

        return $images;
    }
}