<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MSH_Deactivator {
    public static function deactivate(): void {
        flush_rewrite_rules();
        wp_clear_scheduled_hook( 'msh_daily_cleanup' );
        wp_clear_scheduled_hook( 'msh_connector_sync' );
    }
}
