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
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        $runsTable = $wpdb->prefix . 'detit_runs';
        $runItemsTable = $wpdb->prefix . 'detit_run_items';
        $generationsTable = $wpdb->prefix . 'detit_generations';

        $runsSchema = "CREATE TABLE {$runsTable} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'pending',
		total bigint(20) unsigned NOT NULL DEFAULT 0,
		pending bigint(20) unsigned NOT NULL DEFAULT 0,
		running bigint(20) unsigned NOT NULL DEFAULT 0,
		completed bigint(20) unsigned NOT NULL DEFAULT 0,
		failed bigint(20) unsigned NOT NULL DEFAULT 0,
		settings_json longtext NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY user_id (user_id),
		KEY status (status),
		KEY created_at (created_at)
	) {$charsetCollate};";

        $runItemsSchema = "CREATE TABLE {$runItemsTable} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		run_id bigint(20) unsigned NOT NULL,
		product_id bigint(20) unsigned NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'pending',
		attempt_count smallint(5) unsigned NOT NULL DEFAULT 0,
		action_id bigint(20) unsigned NULL DEFAULT NULL,
		error_code varchar(100) NULL DEFAULT NULL,
		error_message text NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY run_id (run_id),
		KEY product_id (product_id),
		KEY status (status)
	) {$charsetCollate};";


        $generationsSchema = "CREATE TABLE {$generationsTable} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        product_id bigint(20) unsigned NOT NULL,
        run_id bigint(20) unsigned NULL DEFAULT NULL,
        user_id bigint(20) unsigned NOT NULL,
        operation varchar(30) NOT NULL DEFAULT 'generate',
        provider varchar(100) NULL DEFAULT NULL,
        model varchar(191) NULL DEFAULT NULL,
        template varchar(100) NULL DEFAULT NULL,
        language varchar(20) NULL DEFAULT NULL,
        before_snapshot longtext NULL,
        generated_snapshot longtext NULL,
        applied_fields longtext NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY product_id (product_id),
        KEY run_id (run_id),
        KEY user_id (user_id),
        KEY status (status),
        KEY created_at (created_at)
    ) {$charsetCollate};";

        return [
            $runsSchema,
            $runItemsSchema,
            $generationsSchema,
        ];
    }
}
