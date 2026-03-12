<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'msh_%'" );
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_msh_%'" );
$wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE '_msh_%'" );

$tables = [
    $wpdb->prefix . 'msh_redirects',
    $wpdb->prefix . 'msh_redirects_cache',
    $wpdb->prefix . 'msh_404_logs',
    $wpdb->prefix . 'msh_internal_links',
    $wpdb->prefix . 'msh_internal_meta',
    $wpdb->prefix . 'msh_analytics',
    $wpdb->prefix . 'msh_schema_templates',
];

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}
