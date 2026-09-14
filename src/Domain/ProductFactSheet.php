<?php

namespace DetIt\Domain;

if (! defined('ABSPATH')) {
    exit;
}

final class ProductFactSheet
{
    /**
     * @param array<int, array{id:int,name:string,slug:string}> $categories
     * @param array<int, array{id:int,name:string,slug:string}> $tags
     * @param array<int, array{
     *     name:string,
     *     label:string,
     *     options:array<int,string>,
     *     visible:bool,
     *     variation:bool
     * }> $attributes
     * @param array{
     *     length:string,
     *     width:string,
     *     height:string
     * } $dimensions
     */
    public function __construct(
        private int $productId,
        private string $title,
        private string $sku,
        private string $type,
        private string $existingDescription,
        private string $existingShortDescription,
        private array $categories,
        private array $tags,
        private array $attributes,
        private string $weight,
        private array $dimensions
    ) {
    }

    public function productId(): int
    {
        return $this->productId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function sku(): string
    {
        return $this->sku;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function existingDescription(): string
    {
        return $this->existingDescription;
    }

    public function existingShortDescription(): string
    {
        return $this->existingShortDescription;
    }

    public function categories(): array
    {
        return $this->categories;
    }

    public function tags(): array
    {
        return $this->tags;
    }

    public function attributes(): array
    {
        return $this->attributes;
    }

    public function weight(): string
    {
        return $this->weight;
    }

    public function dimensions(): array
    {
        return $this->dimensions;
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,

            'identity' => [
                'title' => $this->title,
                'sku'   => $this->sku,
                'type'  => $this->type,
            ],

            'existing_content' => [
                'description' =>
                    $this->existingDescription,

                'short_description' =>
                    $this->existingShortDescription,
            ],

            'taxonomy' => [
                'categories' => $this->categories,
                'tags'       => $this->tags,
            ],

            'attributes' => $this->attributes,

            'physical' => [
                'weight'     => $this->weight,
                'dimensions' => $this->dimensions,
            ],
        ];
    }
}