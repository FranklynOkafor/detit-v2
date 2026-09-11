<?php

namespace DetIt\Storage;

if (! defined('ABSPATH')) {
    exit;
}

class DatabaseInstaller
{
    private const VERSION_OPTION = 'detit_db_version';

    public function install(): void
    {
        $this->createOrUpdateTables();

        update_option(
            self::VERSION_OPTION,
            DETIT_DB_VERSION,
            false
        );
    }

    public function maybeUpgrade(): void
    {
        $installedVersion = (string) get_option(
            self::VERSION_OPTION,
            '0.0.0'
        );

        if (version_compare($installedVersion, DETIT_DB_VERSION, '>=')) {
            return;
        }

        $this->install();
    }

    private function createOrUpdateTables(): void
    {
        $schemas = $this->getTableSchemas();

        if ($schemas === []) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ($schemas as $schema) {
            dbDelta($schema);
        }
    }

    private function getTableSchemas(): array
    {
        // Stages 11–14 will add DetIt's table schemas here.
        return [];
    }
}
