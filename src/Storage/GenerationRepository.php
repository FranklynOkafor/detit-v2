<?php

namespace DetIt\Storage;

if (! defined('ABSPATH')) {
    exit;
}

class GenerationRepository
{
    private \wpdb $wpdb;

    private string $table;

    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'detit_generations';
    }

    public function create(array $data): int|false
    {
        if (
            empty($data['product_id'])
            || empty($data['user_id'])
        ) {
            return false;
        }

        $now = current_time('mysql', true);

        $insertData = [
            'product_id'         => (int) $data['product_id'],
            'run_id'             => isset($data['run_id'])
                ? (int) $data['run_id']
                : null,
            'user_id'            => (int) $data['user_id'],
            'operation'          => $data['operation'] ?? 'generate',
            'provider'           => $data['provider'] ?? null,
            'model'              => $data['model'] ?? null,
            'template'           => $data['template'] ?? null,
            'language'           => $data['language'] ?? null,
            'before_snapshot'    => $this->encodeJson(
                $data['before_snapshot'] ?? null
            ),
            'generated_snapshot' => $this->encodeJson(
                $data['generated_snapshot'] ?? null
            ),
            'applied_fields'     => $this->encodeJson(
                $data['applied_fields'] ?? null
            ),
            'status'             => $data['status'] ?? 'pending',
            'created_at'         => $now,
            'updated_at'         => $now,
        ];

        $inserted = $this->wpdb->insert(
            $this->table,
            $insertData
        );

        if ($inserted === false) {
            return false;
        }

        return (int) $this->wpdb->insert_id;
    }

    public function find(int $generationId): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d",
                $generationId
            ),
            ARRAY_A
        );

        return $row === null
            ? null
            : $this->hydrate($row);
    }

    public function findByProduct(
        int $productId,
        int $limit = 20
    ): array {
        $limit = max(1, min($limit, 100));

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT *
                FROM {$this->table}
                WHERE product_id = %d
                ORDER BY id DESC
                LIMIT %d",
                $productId,
                $limit
            ),
            ARRAY_A
        );

        return array_map(
            [$this, 'hydrate'],
            $rows
        );
    }

    public function update(
        int $generationId,
        array $changes
    ): bool {
        $allowedFields = [
            'operation',
            'provider',
            'model',
            'template',
            'language',
            'before_snapshot',
            'generated_snapshot',
            'applied_fields',
            'status',
        ];

        $data = array_intersect_key(
            $changes,
            array_flip($allowedFields)
        );

        foreach (
            [
                'before_snapshot',
                'generated_snapshot',
                'applied_fields',
            ] as $jsonField
        ) {
            if (array_key_exists($jsonField, $data)) {
                $data[$jsonField] = $this->encodeJson(
                    $data[$jsonField]
                );
            }
        }

        if ($data === []) {
            return false;
        }

        $data['updated_at'] = current_time('mysql', true);

        $result = $this->wpdb->update(
            $this->table,
            $data,
            ['id' => $generationId]
        );

        return $result !== false;
    }

    private function encodeJson(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        $encoded = wp_json_encode($value);

        return $encoded === false
            ? null
            : $encoded;
    }

    private function hydrate(array $row): array
    {
        foreach (
            [
                'before_snapshot',
                'generated_snapshot',
                'applied_fields',
            ] as $jsonField
        ) {
            if (empty($row[$jsonField])) {
                $row[$jsonField] = null;

                continue;
            }

            $decoded = json_decode(
                $row[$jsonField],
                true
            );

            $row[$jsonField] = is_array($decoded)
                ? $decoded
                : null;
        }

        return $row;
    }
}
