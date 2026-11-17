<?php

declare(strict_types=1);

namespace DetIt\WooCommerce;

if (! defined('ABSPATH')) {
    exit;
}

final class ProductImagePreparer
{
    private const MAX_DIMENSION = 1024;

    private const MAX_BYTES = 2 * 1024 * 1024;

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function prepare(int $attachmentId): ?array
    {
        if (
            $attachmentId <= 0
            || ! wp_attachment_is_image($attachmentId)
        ) {
            return null;
        }

        /*
         * Get the image's local file path.
         * Never assume its public URL is a local path.
         */
        $originalPath = get_attached_file($attachmentId);

        if (
            ! is_string($originalPath)
            || $originalPath === ''
            || ! is_file($originalPath)
        ) {
            return null;
        }

        /*
         * WordPress records its generated image sizes
         * in attachment metadata.
         */
        $metadata = wp_get_attachment_metadata(
            $attachmentId
        );

        $sizes = (
            is_array($metadata)
            && isset($metadata['sizes'])
            && is_array($metadata['sizes'])
        ) ? $metadata['sizes'] : [];

        $candidates = [];

        /*
         * Prefer existing resized versions.
         * Do not create unnecessary new image files.
         */
        foreach (
            ['medium_large', 'large', 'medium']
            as $sizeName
        ) {
            if (
                empty($sizes[$sizeName]['file'])
                || ! is_string($sizes[$sizeName]['file'])
            ) {
                continue;
            }

            $candidates[] = [
                'name' => $sizeName,
                'path' => dirname($originalPath)
                    . DIRECTORY_SEPARATOR
                    . basename($sizes[$sizeName]['file']),
            ];
        }

        /*
         * The original is a fallback ONLY if it
         * already satisfies our safety limits.
         */
        $candidates[] = [
            'name' => 'full',
            'path' => $originalPath,
        ];

        foreach ($candidates as $candidate) {
            $path = $candidate['path'];

            if (! is_file($path) || ! is_readable($path)) {
                continue;
            }

            $bytes = @filesize($path);

            if (
                ! is_int($bytes)
                || $bytes <= 0
                || $bytes > self::MAX_BYTES
            ) {
                continue;
            }

            /*
             * Check the actual image dimensions and MIME.
             */
            $imageInfo = @getimagesize($path);

            if (
                ! is_array($imageInfo)
                || ! isset(
                    $imageInfo[0],
                    $imageInfo[1],
                    $imageInfo['mime']
                )
            ) {
                continue;
            }

            $width = (int) $imageInfo[0];
            $height = (int) $imageInfo[1];
            $mimeType = (string) $imageInfo['mime'];

            if (
                $width < 1
                || $height < 1
                || $width > self::MAX_DIMENSION
                || $height > self::MAX_DIMENSION
                || ! in_array(
                    $mimeType,
                    self::ALLOWED_MIME_TYPES,
                    true
                )
            ) {
                continue;
            }

            $contents = @file_get_contents($path);

            if (
                ! is_string($contents)
                || $contents === ''
            ) {
                continue;
            }

            return [
                'attachment_id' => $attachmentId,
                'source_size' => $candidate['name'],
                'mime_type' => $mimeType,
                'width' => $width,
                'height' => $height,
                'bytes' => strlen($contents),
                'data_uri' => 'data:'
                    . $mimeType
                    . ';base64,'
                    . base64_encode($contents),
            ];
        }

        return null;
    }
}
