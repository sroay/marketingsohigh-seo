<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MSH_Module_Image_SEO {

    public function __construct() {
        add_filter( 'the_content', [ $this, 'inject_attributes' ], 99 );
        add_filter( 'post_thumbnail_html', [ $this, 'inject_attributes' ], 99 );
    }

    public function inject_attributes( string $content ): string {
        if ( empty( $content ) ) return $content;

        $settings = get_option( 'msh_general_settings', [] );

        return preg_replace_callback( '/<img([^>]*)>/i', function( $match ) use ( $settings ) {
            $tag  = $match[0];
            $attrs = $match[1];

            // Extract existing alt
            $has_alt = preg_match( '/alt=["\']([^"\']*)["\']/', $attrs, $alt_match );
            $alt_val = $has_alt ? $alt_match[1] : '';

            // Extract src for filename
            preg_match( '/src=["\']([^"\']+)["\']/', $attrs, $src_match );
            $src = $src_match[1] ?? '';
            $filename = pathinfo( wp_parse_url( $src, PHP_URL_PATH ) ?: '', PATHINFO_FILENAME );
            $filename = str_replace( [ '-', '_' ], ' ', $filename );
            $filename = preg_replace( '/\d{2,}x\d{2,}$/', '', $filename ); // Remove dimensions
            $filename = trim( $filename );

            // Auto ALT
            if ( ! $has_alt || $alt_val === '' ) {
                $post  = get_post();
                $title = $post ? $post->post_title : '';

                $template = $settings['image_alt_template'] ?? '%filename%';
                $alt_text = str_replace(
                    [ '%filename%', '%title%', '%name%' ],
                    [ $filename, $title, get_bloginfo( 'name' ) ],
                    $template
                );
                $alt_text = ucwords( trim( $alt_text ) );

                if ( $has_alt ) {
                    $tag = preg_replace( '/alt=["\'][^"\']*["\']/', 'alt="' . esc_attr( $alt_text ) . '"', $tag );
                } else {
                    $tag = str_replace( '<img', '<img alt="' . esc_attr( $alt_text ) . '"', $tag );
                }
            }

            // Auto Title
            if ( ! preg_match( '/\btitle=["\']/', $attrs ) ) {
                $title_template = $settings['image_title_template'] ?? '%filename%';
                $title_text = str_replace(
                    [ '%filename%', '%title%', '%name%' ],
                    [ $filename, get_the_title() ?: '', get_bloginfo( 'name' ) ],
                    $title_template
                );
                $tag = str_replace( '<img', '<img title="' . esc_attr( ucwords( trim( $title_text ) ) ) . '"', $tag );
            }

            return $tag;
        }, $content );
    }
}
