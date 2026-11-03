<?php

/**
 * DetIt Free uninstall cleanup.
 *
 * Only runs when a site administrator uninstalls the plugin using WordPress.
 * Do not revert WooCommerce product content: those were explicit merchant
 * changes and may have been further edited since DetIt originally applied them.
 */
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// The plugin owns these options and audit/history tables only.
// Product posts, terms, metadata and WooCommerce settings are never changed.
delete_option('detit_brand_profile');
delete_option('detit_db_version');

foreach ([
    'detit_runs',
    'detit_run_items',
    'detit_generations',
    'detit_product_index',
] as $suffix) {
    $table = $wpdb->prefix . $suffix;

    // %i safely quotes SQL identifiers (available since WP 6.2).
    $wpdb->query(
        $wpdb->prepare('DROP TABLE IF EXISTS %i', $table)
    );
}
