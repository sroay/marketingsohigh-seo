<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MSH_Module_Redirects {

    public function __construct() {
        add_action( 'template_redirect', [ $this, 'check_redirect' ], 1 );
        add_action( 'post_updated', [ $this, 'auto_redirect_on_slug_change' ], 10, 3 );
    }

    public function check_redirect(): void {
        if ( is_admin() ) return;

        $request = $_SERVER['REQUEST_URI'] ?? '';
        $redirect = $this->find_redirect( $request );

        if ( ! $redirect ) return;

        // Increment hit counter
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}msh_redirects SET hits = hits + 1 WHERE id = %d",
            $redirect['id']
        ) );

        $type = (int) $redirect['type'];

        if ( $type === 410 ) {
            status_header( 410 );
            nocache_headers();
            echo '<h1>410 Gone</h1><p>This content has been permanently removed.</p>';
            exit;
        }

        if ( $type === 451 ) {
            status_header( 451 );
            nocache_headers();
            echo '<h1>451 Unavailable For Legal Reasons</h1>';
            exit;
        }

        wp_redirect( $redirect['destination'], $type );
        exit;
    }

    private function find_redirect( string $url ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'msh_redirects';

        // Check cache first
        $cache_table = $wpdb->prefix . 'msh_redirects_cache';
        $cached = $wpdb->get_row( $wpdb->prepare(
            "SELECT redirect_id FROM {$cache_table} WHERE from_url = %s",
            $url
        ), ARRAY_A );

        if ( $cached ) {
            if ( (int) $cached['redirect_id'] === 0 ) return null;
            return $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d", $cached['redirect_id']
            ), ARRAY_A );
        }

        // Exact match
        $redirect = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE source = %s AND match_mode = 'exact'",
            $url
        ), ARRAY_A );

        if ( ! $redirect ) {
            // Pattern matches
            $all = $wpdb->get_results(
                "SELECT * FROM {$table} WHERE match_mode != 'exact' ORDER BY id ASC",
                ARRAY_A
            );

            foreach ( $all as $r ) {
                if ( $this->matches( $r, $url ) ) {
                    $redirect = $r;
                    break;
                }
            }
        }

        // Cache the result
        $wpdb->replace( $cache_table, [
            'from_url'    => $url,
            'redirect_id' => $redirect ? $redirect['id'] : 0,
        ] );

        return $redirect;
    }

    private function matches( array $rule, string $url ): bool {
        $source = $rule['source'];
        return match ( $rule['match_mode'] ) {
            'contains'    => str_contains( $url, $source ),
            'starts_with' => str_starts_with( $url, $source ),
            'ends_with'   => str_ends_with( $url, $source ),
            'regex'       => (bool) @preg_match( '#' . $source . '#', $url ),
            default       => $url === $source,
        };
    }

    public function auto_redirect_on_slug_change( int $post_id, \WP_Post $post_after, \WP_Post $post_before ): void {
        if ( $post_before->post_name === $post_after->post_name ) return;
        if ( $post_after->post_status !== 'publish' ) return;

        $old_url = str_replace( home_url(), '', get_permalink( $post_before ) );
        $new_url = get_permalink( $post_after );

        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'msh_redirects', [
            'source'      => $old_url,
            'destination' => $new_url,
            'type'        => 301,
            'match_mode'  => 'exact',
        ] );

        // Clear cache
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}msh_redirects_cache" );
    }

    public static function create( array $data ): int {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'msh_redirects', [
            'source'      => $data['source'],
            'destination' => $data['destination'] ?? '',
            'type'        => $data['type'] ?? 301,
            'match_mode'  => $data['match_mode'] ?? 'exact',
        ] );
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}msh_redirects_cache" );
        return $wpdb->insert_id;
    }

    public static function delete( int $id ): bool {
        global $wpdb;
        $result = $wpdb->delete( $wpdb->prefix . 'msh_redirects', [ 'id' => $id ] );
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}msh_redirects_cache" );
        return (bool) $result;
    }
}
