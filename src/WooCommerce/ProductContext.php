<?php

namespace DetIt\WooCommerce;

if (! defined('ABSPATH')) {
    exit;
}

final class ProductContext
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
     * @param array{length:string,width:string,height:string} $dimensions
     * @param array{id:int,url:string,alt:string}|null $featuredImage
     * @param array<int, array{id:int,url:string,alt:string}> $galleryImages
     */
    public function __construct(
        private int $id,
        private string $title,
        private string $sku,
        private string $type,
        private string $description,
        private string $shortDescription,
        private array $categories,
        private array $tags,
        private array $attributes,
        private string $weight,
        private array $dimensions,
        private ?array $featuredImage,
        private array $galleryImages
    ) {
    }

    public function id(): int
    {
        return $this->id;
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

    public function description(): string
    {
        return $this->description;
    }

    public function shortDescription(): string
    {
        return $this->shortDescription;
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

    public function featuredImage(): ?array
    {
        return $this->featuredImage;
    }

    public function galleryImages(): array
    {
        return $this->galleryImages;
    }

    public function toArray(): array
    {
        return [
            'id'                => $this->id,
            'title'             => $this->title,
            'sku'               => $this->sku,
            'type'              => $this->type,
            'description'       => $this->description,
            'short_description' => $this->shortDescription,
            'categories'        => $this->categories,
            'tags'              => $this->tags,
            'attributes'        => $this->attributes,
            'weight'            => $this->weight,
            'dimensions'        => $this->dimensions,
            'featured_image'    => $this->featuredImage,
            'gallery_images'    => $this->galleryImages,
        ];
    }
}