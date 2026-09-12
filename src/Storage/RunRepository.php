<?php

namespace DetIt\Storage;

if (! defined('ABSPATH')) {
    exit;
}

class RunRepository
{
    private \wpdb $wpdb;

    private string $table;

    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'detit_runs';
    }

    public function create(int $userId, array $settings = []): int|false
    {
        $now = current_time('mysql', true);

        $inserted = $this->wpdb->insert(
            $this->table,
            [
                'user_id'       => $userId,
                'status'        => 'pending',
                'total'         => 0,
                'pending'       => 0,
                'running'       => 0,
                'completed'     => 0,
                'failed'        => 0,
                'settings_json' => $settings === []
                    ? null
                    : wp_json_encode($settings),
                'created_at'    => $now,
                'updated_at'    => $now,
            ]
        );

        if ($inserted === false) {
            return false;
        }

        return (int) $this->wpdb->insert_id;
    }

    public function find(int $runId): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d",
                $runId
            ),
            ARRAY_A
        );

        if ($row === null) {
            return null;
        }

        if (! empty($row['settings_json'])) {
            $decoded = json_decode($row['settings_json'], true);

            $row['settings'] = is_array($decoded) ? $decoded : [];
        } else {
            $row['settings'] = [];
        }

        return $row;
    }

    public function update(int $runId, array $changes): bool
    {
        $allowedFields = [
            'status',
            'total',
            'pending',
            'running',
            'completed',
            'failed',
            'settings_json',
        ];

        $data = array_intersect_key(
            $changes,
            array_flip($allowedFields)
        );

        if (isset($data['settings_json']) && is_array($data['settings_json'])) {
            $data['settings_json'] = wp_json_encode($data['settings_json']);
        }

        if ($data === []) {
            return false;
        }

        $data['updated_at'] = current_time('mysql', true);

        $result = $this->wpdb->update(
            $this->table,
            $data,
            ['id' => $runId]
        );

        return $result !== false;
    }
}