<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * MSH Connector — Bridges WordPress with the MarketingSoHigh dashboard.
 * Handles bidirectional sync: publish from MSH, send analytics to MSH.
 */
class MSH_Module_Connector {

    private string $api_base = 'https://marketingsohigh.com/api';

    public function __construct() {
        // REST API endpoints for MSH dashboard to call
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );

        // Periodic sync
        add_action( 'msh_connector_sync', [ $this, 'sync_to_dashboard' ] );
        if ( ! wp_next_scheduled( 'msh_connector_sync' ) ) {
            wp_schedule_event( time(), 'twicedaily', 'msh_connector_sync' );
        }
    }

    public function register_routes(): void {
        register_rest_route( 'msh/v1', '/publish', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_publish' ],
            'permission_callback' => [ $this, 'verify_api_key' ],
        ] );

        register_rest_route( 'msh/v1', '/meta', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_meta_update' ],
            'permission_callback' => [ $this, 'verify_api_key' ],
        ] );

        register_rest_route( 'msh/v1', '/redirect', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_redirect' ],
            'permission_callback' => [ $this, 'verify_api_key' ],
        ] );

        register_rest_route( 'msh/v1', '/status', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_status' ],
            'permission_callback' => [ $this, 'verify_api_key' ],
        ] );

        register_rest_route( 'msh/v1', '/content-inventory', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_content_inventory' ],
            'permission_callback' => [ $this, 'verify_api_key' ],
        ] );

        register_rest_route( 'msh/v1', '/keywords', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_keyword_sync' ],
            'permission_callback' => [ $this, 'verify_api_key' ],
        ] );
    }

    public function verify_api_key( \WP_REST_Request $request ): bool {
        $key = $request->get_header( 'X-MSH-API-Key' );
        if ( ! $key ) {
            $key = $request->get_param( 'api_key' );
        }
        $stored = get_option( 'msh_connector_api_key' );
        return $key && $stored && hash_equals( $stored, $key );
    }

    public function handle_publish( \WP_REST_Request $request ): \WP_REST_Response {
        $data = $request->get_json_params();

        $post_data = [
            'post_title'   => sanitize_text_field( $data['title'] ?? '' ),
            'post_content' => wp_kses_post( $data['content'] ?? '' ),
            'post_status'  => $data['status'] ?? 'draft',
            'post_type'    => $data['post_type'] ?? 'post',
            'post_name'    => sanitize_title( $data['slug'] ?? '' ),
            'post_excerpt'  => sanitize_textarea_field( $data['excerpt'] ?? '' ),
        ];

        // Update existing or create new
        if ( ! empty( $data['post_id'] ) ) {
            $post_data['ID'] = (int) $data['post_id'];
            $post_id = wp_update_post( $post_data, true );
        } else {
            $post_id = wp_insert_post( $post_data, true );
        }

        if ( is_wp_error( $post_id ) ) {
            return new \WP_REST_Response( [ 'error' => $post_id->get_error_message() ], 400 );
        }

        // Set categories
        if ( ! empty( $data['categories'] ) ) {
            $cat_ids = [];
            foreach ( (array) $data['categories'] as $cat_name ) {
                $term = get_term_by( 'name', $cat_name, 'category' );
                if ( ! $term ) {
                    $result = wp_insert_term( $cat_name, 'category' );
                    $cat_ids[] = is_wp_error( $result ) ? 0 : $result['term_id'];
                } else {
                    $cat_ids[] = $term->term_id;
                }
            }
            wp_set_post_categories( $post_id, array_filter( $cat_ids ) );
        }

        // Set tags
        if ( ! empty( $data['tags'] ) ) {
            wp_set_post_tags( $post_id, (array) $data['tags'] );
        }

        // Set SEO meta
        $meta_fields = [
            'seo_title'       => '_msh_title',
            'seo_description' => '_msh_description',
            'focus_keyword'   => '_msh_focus_keyword',
            'canonical_url'   => '_msh_canonical_url',
            'og_title'        => '_msh_og_title',
            'og_description'  => '_msh_og_description',
            'og_image'        => '_msh_og_image',
            'schema_type'     => '_msh_schema_type',
        ];

        foreach ( $meta_fields as $key => $meta_key ) {
            if ( isset( $data[ $key ] ) ) {
                update_post_meta( $post_id, $meta_key, $data[ $key ] );
            }
        }

        // Schema data
        if ( ! empty( $data['schema_data'] ) ) {
            update_post_meta( $post_id, '_msh_schema_data', $data['schema_data'] );
        }

        // Featured image from URL
        if ( ! empty( $data['featured_image_url'] ) ) {
            $media_id = $this->sideload_image( $data['featured_image_url'], $post_id, $data['featured_image_alt'] ?? '' );
            if ( $media_id ) {
                set_post_thumbnail( $post_id, $media_id );
            }
        }

        // Trigger IndexNow
        if ( $post_data['post_status'] === 'publish' ) {
            $indexnow = MSH_Loader::get_instance()->get_module( 'indexnow' );
            if ( $indexnow ) {
                $indexnow->submit_url( get_permalink( $post_id ) );
            }
        }

        return new \WP_REST_Response( [
            'success' => true,
            'post_id' => $post_id,
            'url'     => get_permalink( $post_id ),
        ] );
    }

    public function handle_meta_update( \WP_REST_Request $request ): \WP_REST_Response {
        $data    = $request->get_json_params();
        $post_id = (int) ( $data['post_id'] ?? 0 );

        if ( ! $post_id || ! get_post( $post_id ) ) {
            return new \WP_REST_Response( [ 'error' => 'Invalid post ID' ], 400 );
        }

        $allowed = [ '_msh_title', '_msh_description', '_msh_focus_keyword', '_msh_canonical_url', '_msh_robots', '_msh_og_title', '_msh_og_description', '_msh_og_image', '_msh_twitter_title', '_msh_twitter_description', '_msh_twitter_image', '_msh_schema_type', '_msh_schema_data' ];

        $updated = 0;
        foreach ( $data['meta'] ?? [] as $key => $value ) {
            if ( in_array( $key, $allowed, true ) ) {
                update_post_meta( $post_id, $key, $value );
                $updated++;
            }
        }

        return new \WP_REST_Response( [ 'success' => true, 'updated' => $updated ] );
    }

    public function handle_redirect( \WP_REST_Request $request ): \WP_REST_Response {
        $data = $request->get_json_params();
        $id = MSH_Module_Redirects::create( [
            'source'      => $data['source'] ?? '',
            'destination' => $data['destination'] ?? '',
            'type'        => $data['type'] ?? 301,
            'match_mode'  => $data['match_mode'] ?? 'exact',
        ] );

        return new \WP_REST_Response( [ 'success' => true, 'redirect_id' => $id ] );
    }

    public function handle_status( \WP_REST_Request $request ): \WP_REST_Response {
        return new \WP_REST_Response( [
            'plugin_version' => MSH_VERSION,
            'wp_version'     => get_bloginfo( 'version' ),
            'php_version'    => phpversion(),
            'site_url'       => home_url(),
            'site_name'      => get_bloginfo( 'name' ),
            'modules'        => get_option( 'msh_modules', [] ),
            'post_count'     => wp_count_posts()->publish,
            'page_count'     => wp_count_posts( 'page' )->publish,
            'last_sync'      => get_option( 'msh_connector_last_sync', '' ),
        ] );
    }

    public function handle_content_inventory( \WP_REST_Request $request ): \WP_REST_Response {
        $posts = get_posts( [
            'post_type'      => [ 'post', 'page' ],
            'post_status'    => 'publish',
            'posts_per_page' => 500,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ] );

        $inventory = [];
        foreach ( $posts as $p ) {
            $inventory[] = [
                'id'              => $p->ID,
                'title'           => $p->post_title,
                'url'             => get_permalink( $p ),
                'type'            => $p->post_type,
                'modified'        => $p->post_modified,
                'seo_title'       => get_post_meta( $p->ID, '_msh_title', true ),
                'seo_description' => get_post_meta( $p->ID, '_msh_description', true ),
                'focus_keyword'   => get_post_meta( $p->ID, '_msh_focus_keyword', true ),
                'seo_score'       => get_post_meta( $p->ID, '_msh_seo_score', true ),
            ];
        }

        return new \WP_REST_Response( [ 'posts' => $inventory, 'total' => count( $inventory ) ] );
    }

    public function handle_keyword_sync( \WP_REST_Request $request ): \WP_REST_Response {
        $data = $request->get_json_params();
        $keywords = $data['keywords'] ?? [];
        $updated = 0;

        foreach ( $keywords as $kw ) {
            if ( ! empty( $kw['post_id'] ) && ! empty( $kw['keyword'] ) ) {
                update_post_meta( (int) $kw['post_id'], '_msh_focus_keyword', sanitize_text_field( $kw['keyword'] ) );
                $updated++;
            }
        }

        return new \WP_REST_Response( [ 'success' => true, 'updated' => $updated ] );
    }

    public function sync_to_dashboard(): void {
        $api_key = get_option( 'msh_connector_api_key' );
        $site_id = get_option( 'msh_connector_site_id' );
        if ( ! $api_key || ! $site_id ) return;

        // Send 404 patterns
        $logs = MSH_Module_404_Monitor::get_logs( 100, 1, 'hits', 'DESC' );

        wp_remote_post( $this->api_base . '/wp-sync', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => wp_json_encode( [
                'site_id'    => $site_id,
                'type'       => 'sync',
                '404_logs'   => array_slice( $logs, 0, 50 ),
                'post_count' => wp_count_posts()->publish,
                'page_count' => wp_count_posts( 'page' )->publish,
            ] ),
            'timeout' => 30,
        ] );

        update_option( 'msh_connector_last_sync', current_time( 'mysql' ) );
    }

    private function sideload_image( string $url, int $post_id, string $alt = '' ): ?int {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url( $url );
        if ( is_wp_error( $tmp ) ) return null;

        $filename = basename( wp_parse_url( $url, PHP_URL_PATH ) ) ?: 'image.jpg';
        $file_array = [ 'name' => $filename, 'tmp_name' => $tmp ];

        $media_id = media_handle_sideload( $file_array, $post_id );
        if ( is_wp_error( $media_id ) ) {
            @unlink( $tmp );
            return null;
        }

        if ( $alt ) {
            update_post_meta( $media_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
        }

        return $media_id;
    }
}
