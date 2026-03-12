<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MSH_Module_IndexNow {

    private string $api_url = 'https://api.indexnow.org/indexnow';

    public function __construct() {
        add_action( 'transition_post_status', [ $this, 'on_post_status_change' ], 10, 3 );
        add_action( 'delete_post', [ $this, 'on_post_delete' ] );
        add_action( 'template_redirect', [ $this, 'serve_key_file' ] );
    }

    public function on_post_status_change( string $new, string $old, \WP_Post $post ): void {
        if ( $new !== 'publish' ) return;
        if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) return;

        // Check noindex
        $robots = get_post_meta( $post->ID, '_msh_robots', true );
        if ( is_array( $robots ) && in_array( 'noindex', $robots, true ) ) return;

        $this->submit_url( get_permalink( $post ) );
        update_post_meta( $post->ID, '_msh_indexnow_submitted_at', current_time( 'mysql' ) );
    }

    public function on_post_delete( int $post_id ): void {
        $url = get_permalink( $post_id );
        if ( $url ) {
            $this->submit_url( $url );
        }
    }

    public function submit_url( string $url ): bool {
        $key = get_option( 'msh_indexnow_key' );
        if ( ! $key ) return false;

        $response = wp_remote_post( $this->api_url, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( [
                'host'    => wp_parse_url( home_url(), PHP_URL_HOST ),
                'key'     => $key,
                'keyLocation' => home_url( "/{$key}.txt" ),
                'urlList' => [ $url ],
            ] ),
            'timeout' => 10,
        ] );

        $code = wp_remote_retrieve_response_code( $response );
        return $code >= 200 && $code < 300;
    }

    public function submit_urls( array $urls ): bool {
        $key = get_option( 'msh_indexnow_key' );
        if ( ! $key || empty( $urls ) ) return false;

        // IndexNow supports up to 10,000 URLs per request
        $chunks = array_chunk( $urls, 10000 );
        $success = true;

        foreach ( $chunks as $chunk ) {
            $response = wp_remote_post( $this->api_url, [
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body'    => wp_json_encode( [
                    'host'        => wp_parse_url( home_url(), PHP_URL_HOST ),
                    'key'         => $key,
                    'keyLocation' => home_url( "/{$key}.txt" ),
                    'urlList'     => $chunk,
                ] ),
                'timeout' => 30,
            ] );

            $code = wp_remote_retrieve_response_code( $response );
            if ( $code < 200 || $code >= 300 ) {
                $success = false;
            }
        }

        return $success;
    }

    public function serve_key_file(): void {
        $key = get_option( 'msh_indexnow_key' );
        if ( ! $key ) return;

        $request = trim( $_SERVER['REQUEST_URI'] ?? '', '/' );
        if ( $request === $key . '.txt' ) {
            header( 'Content-Type: text/plain' );
            echo $key;
            exit;
        }
    }
}
