<?php

namespace DetIt\Storage;

if (! defined('ABSPATH')) {
    exit;
}

class RunItemRepository
{
    private \wpdb $wpdb;

    private string $table;

    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'detit_run_items';
    }

    public function create(int $runId, int $productId): int|false
    {
        $now = current_time('mysql', true);

        $inserted = $this->wpdb->insert(
            $this->table,
            [
                'run_id'        => $runId,
                'product_id'    => $productId,
                'status'        => 'pending',
                'attempt_count' => 0,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]
        );

        if ($inserted === false) {
            return false;
        }

        return (int) $this->wpdb->insert_id;
    }

    public function find(int $itemId): ?array
    {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d",
                $itemId
            ),
            ARRAY_A
        ) ?: null;
    }

    public function findByRun(int $runId): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT *
                FROM {$this->table}
                WHERE run_id = %d
                ORDER BY id ASC",
                $runId
            ),
            ARRAY_A
        );
    }

    public function update(int $itemId, array $changes): bool
    {
        $allowedFields = [
            'status',
            'attempt_count',
            'action_id',
            'error_code',
            'error_message',
        ];

        $data = array_intersect_key(
            $changes,
            array_flip($allowedFields)
        );

        if ($data === []) {
            return false;
        }

        $data['updated_at'] = current_time('mysql', true);

        $result = $this->wpdb->update(
            $this->table,
            $data,
            ['id' => $itemId]
        );

        return $result !== false;
    }
}