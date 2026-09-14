<?php

namespace DetIt\WooCommerce;

if (! defined('ABSPATH')) {
    exit;
}

final class ProductContext
{
    public function __construct(
        private int $id,
        private string $title,
        private string $type
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

    public function type(): string
    {
        return $this->type;
    }

    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     type: string
     * }
     */
    public function toArray(): array
    {
        return [
            'id'    => $this->id,
            'title' => $this->title,
            'type'  => $this->type,
        ];
    }
}