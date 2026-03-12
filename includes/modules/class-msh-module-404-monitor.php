<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MSH_Module_404_Monitor {

    public function __construct() {
        add_action( 'template_redirect', [ $this, 'log_404' ], 999 );
        add_action( 'msh_daily_cleanup', [ $this, 'cleanup_old_logs' ] );

        if ( ! wp_next_scheduled( 'msh_daily_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'msh_daily_cleanup' );
        }
    }

    public function log_404(): void {
        if ( ! is_404() ) return;

        global $wpdb;
        $table = $wpdb->prefix . 'msh_404_logs';
        $url   = $_SERVER['REQUEST_URI'] ?? '';

        // Try to update existing entry
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE url = %s", $url
        ) );

        if ( $existing ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$table} SET hits = hits + 1, updated_at = NOW(), referrer = %s, user_agent = %s WHERE id = %d",
                wp_get_referer() ?: '',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $existing
            ) );
        } else {
            $wpdb->insert( $table, [
                'url'        => $url,
                'referrer'   => wp_get_referer() ?: '',
                'user_agent' => substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500 ),
                'ip'         => $this->get_ip(),
                'hits'       => 1,
            ] );
        }
    }

    public function cleanup_old_logs(): void {
        global $wpdb;
        $retention = apply_filters( 'msh_404_retention_days', 30 );
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}msh_404_logs WHERE updated_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $retention
        ) );
    }

    private function get_ip(): string {
        $headers = [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ];
        foreach ( $headers as $h ) {
            if ( ! empty( $_SERVER[ $h ] ) ) {
                $ip = explode( ',', $_SERVER[ $h ] )[0];
                return trim( $ip );
            }
        }
        return '';
    }

    public static function get_logs( int $per_page = 50, int $page = 1, string $order_by = 'hits', string $order = 'DESC' ): array {
        global $wpdb;
        $table  = $wpdb->prefix . 'msh_404_logs';
        $offset = ( $page - 1 ) * $per_page;
        $allowed = [ 'hits', 'url', 'created_at', 'updated_at' ];
        $order_by = in_array( $order_by, $allowed ) ? $order_by : 'hits';
        $order    = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

        return $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY {$order_by} {$order} LIMIT {$per_page} OFFSET {$offset}",
            ARRAY_A
        );
    }

    public static function get_total(): int {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}msh_404_logs" );
    }
}
