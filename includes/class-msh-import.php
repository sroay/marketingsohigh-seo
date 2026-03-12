<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MSH_Import {

    public static function init(): void {
        add_action( 'admin_init', [ __CLASS__, 'handle_import' ] );
    }

    public static function handle_import(): void {
        if ( ! isset( $_POST['msh_import_source'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['msh_import_nonce'] ?? '', 'msh_import' ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        $source = sanitize_text_field( $_POST['msh_import_source'] );

        switch ( $source ) {
            case 'yoast':
                $count = self::import_yoast();
                break;
            case 'rankmath':
                $count = self::import_rankmath();
                break;
            case 'aioseo':
                $count = self::import_aioseo();
                break;
            default:
                return;
        }

        add_action( 'admin_notices', function() use ( $count, $source ) {
            echo '<div class="notice notice-success"><p>Imported ' . (int) $count . ' posts from ' . esc_html( ucfirst( $source ) ) . '.</p></div>';
        } );
    }

    public static function import_yoast(): int {
        global $wpdb;

        $mapping = [
            '_yoast_wpseo_title'       => '_msh_title',
            '_yoast_wpseo_metadesc'    => '_msh_description',
            '_yoast_wpseo_focuskw'     => '_msh_focus_keyword',
            '_yoast_wpseo_canonical'   => '_msh_canonical_url',
            '_yoast_wpseo_opengraph-title'       => '_msh_og_title',
            '_yoast_wpseo_opengraph-description'  => '_msh_og_description',
            '_yoast_wpseo_opengraph-image'        => '_msh_og_image',
            '_yoast_wpseo_twitter-title'          => '_msh_twitter_title',
            '_yoast_wpseo_twitter-description'    => '_msh_twitter_description',
            '_yoast_wpseo_twitter-image'          => '_msh_twitter_image',
        ];

        $count = 0;
        foreach ( $mapping as $yoast_key => $msh_key ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != ''",
                $yoast_key
            ) );

            foreach ( $rows as $row ) {
                // Don't overwrite existing MSH data
                $existing = get_post_meta( $row->post_id, $msh_key, true );
                if ( ! $existing ) {
                    update_post_meta( $row->post_id, $msh_key, $row->meta_value );
                    $count++;
                }
            }
        }

        // Import robots meta
        $robots_rows = $wpdb->get_results(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_yoast_wpseo_meta-robots-noindex' AND meta_value = '1'"
        );
        foreach ( $robots_rows as $row ) {
            $existing = get_post_meta( $row->post_id, '_msh_robots', true ) ?: [];
            if ( ! in_array( 'noindex', $existing ) ) {
                $existing[] = 'noindex';
                update_post_meta( $row->post_id, '_msh_robots', $existing );
                $count++;
            }
        }

        return $count;
    }

    public static function import_rankmath(): int {
        global $wpdb;

        $mapping = [
            'rank_math_title'              => '_msh_title',
            'rank_math_description'        => '_msh_description',
            'rank_math_focus_keyword'      => '_msh_focus_keyword',
            'rank_math_canonical_url'      => '_msh_canonical_url',
            'rank_math_facebook_title'     => '_msh_og_title',
            'rank_math_facebook_description' => '_msh_og_description',
            'rank_math_facebook_image'     => '_msh_og_image',
            'rank_math_twitter_title'      => '_msh_twitter_title',
            'rank_math_twitter_description' => '_msh_twitter_description',
            'rank_math_twitter_image'      => '_msh_twitter_image',
            'rank_math_breadcrumb_title'   => '_msh_breadcrumb_title',
        ];

        $count = 0;
        foreach ( $mapping as $rm_key => $msh_key ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != ''",
                $rm_key
            ) );

            foreach ( $rows as $row ) {
                $existing = get_post_meta( $row->post_id, $msh_key, true );
                if ( ! $existing ) {
                    update_post_meta( $row->post_id, $msh_key, $row->meta_value );
                    $count++;
                }
            }
        }

        // Import robots
        $robots_rows = $wpdb->get_results(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'rank_math_robots' AND meta_value != ''"
        );
        foreach ( $robots_rows as $row ) {
            $rm_robots = maybe_unserialize( $row->meta_value );
            if ( is_array( $rm_robots ) && ! empty( $rm_robots ) ) {
                $existing = get_post_meta( $row->post_id, '_msh_robots', true ) ?: [];
                update_post_meta( $row->post_id, '_msh_robots', array_unique( array_merge( $existing, $rm_robots ) ) );
                $count++;
            }
        }

        // Import redirects from RankMath table
        $rm_table = $wpdb->prefix . 'rank_math_redirections';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$rm_table}'" ) ) {
            $redirects = $wpdb->get_results( "SELECT * FROM {$rm_table}", ARRAY_A );
            foreach ( $redirects as $r ) {
                $sources = maybe_unserialize( $r['sources'] ?? '' );
                $source_url = '';
                if ( is_array( $sources ) && ! empty( $sources ) ) {
                    $source_url = $sources[0]['pattern'] ?? '';
                }
                if ( $source_url ) {
                    MSH_Module_Redirects::create( [
                        'source'      => $source_url,
                        'destination' => $r['url_to'] ?? '',
                        'type'        => (int) ( $r['header_code'] ?? 301 ),
                        'match_mode'  => 'exact',
                    ] );
                    $count++;
                }
            }
        }

        return $count;
    }

    public static function import_aioseo(): int {
        global $wpdb;

        $mapping = [
            '_aioseo_title'       => '_msh_title',
            '_aioseo_description' => '_msh_description',
            '_aioseo_keywords'    => '_msh_focus_keyword',
            '_aioseo_og_title'    => '_msh_og_title',
            '_aioseo_og_description' => '_msh_og_description',
        ];

        $count = 0;
        foreach ( $mapping as $aio_key => $msh_key ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != ''",
                $aio_key
            ) );

            foreach ( $rows as $row ) {
                $existing = get_post_meta( $row->post_id, $msh_key, true );
                if ( ! $existing ) {
                    update_post_meta( $row->post_id, $msh_key, $row->meta_value );
                    $count++;
                }
            }
        }

        return $count;
    }
}

MSH_Import::init();
