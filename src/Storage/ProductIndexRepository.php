<?php

namespace DetIt\Storage;

if (! defined('ABSPATH')) {
    exit;
}

class ProductIndexRepository
{
    private \wpdb $wpdb;

    private string $table;

    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'detit_product_index';
    }

    public function findByProduct(int $productId): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT *
                FROM {$this->table}
                WHERE product_id = %d",
                $productId
            ),
            ARRAY_A
        );

        if ($row === null) {
            return null;
        }

        $decodedFlags = json_decode(
            $row['issue_flags'],
            true
        );

        $row['issue_flags'] = is_array($decodedFlags)
            ? $decodedFlags
            : [];

        return $row;
    }

    public function save(
        int $productId,
        array $data
    ): bool {
        $existing = $this->findByProduct($productId);

        $allowedFields = [
            'content_health',
            'issue_flags',
            'source_hash',
            'last_scanned_at',
            'last_optimized_at',
        ];

        $cleanData = array_intersect_key(
            $data,
            array_flip($allowedFields)
        );

        if (array_key_exists('issue_flags', $cleanData)) {
            $flags = $cleanData['issue_flags'];

            $cleanData['issue_flags'] = is_string($flags)
                ? $flags
                : wp_json_encode($flags);
        }

        if ($existing === null) {
            $insertData = array_merge(
                [
                    'product_id'       => $productId,
                    'content_health'   => 0,
                    'issue_flags'      => '[]',
                    'source_hash'      => '',
                    'last_scanned_at'  => null,
                    'last_optimized_at' => null,
                ],
                $cleanData
            );

            $result = $this->wpdb->insert(
                $this->table,
                $insertData
            );

            return $result !== false;
        }

        if ($cleanData === []) {
            return false;
        }

        $result = $this->wpdb->update(
            $this->table,
            $cleanData,
            ['product_id' => $productId]
        );

        return $result !== false;
    }

    public function delete(int $productId): bool
    {
        $result = $this->wpdb->delete(
            $this->table,
            ['product_id' => $productId]
        );

        return $result !== false;
    }
}
