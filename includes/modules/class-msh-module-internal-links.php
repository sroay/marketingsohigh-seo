<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MSH_Module_Internal_Links {

    public function __construct() {
        add_action( 'save_post', [ $this, 'analyze_links' ], 20 );
    }

    public function analyze_links( int $post_id ): void {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;

        $post = get_post( $post_id );
        if ( ! $post || $post->post_status !== 'publish' ) return;
        if ( get_post_meta( $post_id, '_msh_internal_links_processed', true ) === $post->post_modified ) return;

        global $wpdb;
        $table      = $wpdb->prefix . 'msh_internal_links';
        $meta_table = $wpdb->prefix . 'msh_internal_meta';
        $home_host  = wp_parse_url( home_url(), PHP_URL_HOST );

        // Clear existing
        $wpdb->delete( $table, [ 'post_id' => $post_id ] );

        // Extract all links
        preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $post->post_content, $matches, PREG_SET_ORDER );

        $internal = 0;
        $external = 0;

        foreach ( $matches as $m ) {
            $href   = $m[1];
            $anchor = wp_strip_all_tags( $m[2] );
            $is_nofollow = (bool) preg_match( '/rel=["\'][^"\']*nofollow/', $m[0] );

            $link_host = wp_parse_url( $href, PHP_URL_HOST );

            if ( ! $link_host || $link_host === $home_host ) {
                $target_id = url_to_postid( $href ) ?: 0;
                $wpdb->insert( $table, [
                    'post_id'     => $post_id,
                    'target_id'   => $target_id,
                    'target_url'  => $href,
                    'anchor'      => substr( $anchor, 0, 500 ),
                    'type'        => 'internal',
                    'is_nofollow' => $is_nofollow ? 1 : 0,
                ] );
                $internal++;
            } else {
                $wpdb->insert( $table, [
                    'post_id'     => $post_id,
                    'target_id'   => 0,
                    'target_url'  => $href,
                    'anchor'      => substr( $anchor, 0, 500 ),
                    'type'        => 'external',
                    'is_nofollow' => $is_nofollow ? 1 : 0,
                ] );
                $external++;
            }
        }

        // Update meta
        $incoming = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE target_id = %d AND type = 'internal'",
            $post_id
        ) );

        $wpdb->replace( $meta_table, [
            'post_id'      => $post_id,
            'internal_out' => $internal,
            'external_out' => $external,
            'incoming'     => $incoming,
        ] );

        update_post_meta( $post_id, '_msh_internal_links_processed', $post->post_modified );
    }

    public static function get_suggestions( int $post_id, int $limit = 10 ): array {
        $post = get_post( $post_id );
        if ( ! $post ) return [];

        $keyword = get_post_meta( $post_id, '_msh_focus_keyword', true );
        $cats    = wp_get_post_categories( $post_id );

        $args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'post__not_in'   => [ $post_id ],
        ];

        if ( $keyword ) {
            $args['s'] = $keyword;
        } elseif ( ! empty( $cats ) ) {
            $args['category__in'] = $cats;
        }

        $posts = get_posts( $args );
        $suggestions = [];

        foreach ( $posts as $p ) {
            $suggestions[] = [
                'id'    => $p->ID,
                'title' => $p->post_title,
                'url'   => get_permalink( $p ),
            ];
        }

        return $suggestions;
    }
}
