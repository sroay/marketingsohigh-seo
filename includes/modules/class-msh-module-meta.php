<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MSH_Module_Meta {

    public function __construct() {
        add_action( 'wp_head', [ $this, 'output_meta_tags' ], 1 );
        add_filter( 'pre_get_document_title', [ $this, 'filter_title' ], 20 );
        add_filter( 'document_title_separator', [ $this, 'filter_separator' ] );
    }

    public function filter_title( string $title ): string {
        if ( is_singular() ) {
            $custom = get_post_meta( get_the_ID(), '_msh_title', true );
            if ( $custom ) {
                return $this->parse_variables( $custom );
            }
        }

        if ( is_tax() || is_category() || is_tag() ) {
            $term = get_queried_object();
            if ( $term ) {
                $custom = get_term_meta( $term->term_id, '_msh_title', true );
                if ( $custom ) {
                    return $this->parse_variables( $custom, null, $term );
                }
            }
        }

        return $title;
    }

    public function filter_separator( string $sep ): string {
        $settings = get_option( 'msh_general_settings', [] );
        return $settings['separator'] ?? $sep;
    }

    public function output_meta_tags(): void {
        $this->output_description();
        $this->output_robots();
        $this->output_canonical();
        $this->output_open_graph();
        $this->output_twitter_card();
    }

    private function output_description(): void {
        $desc = '';
        if ( is_singular() ) {
            $desc = get_post_meta( get_the_ID(), '_msh_description', true );
        } elseif ( is_tax() || is_category() || is_tag() ) {
            $term = get_queried_object();
            $desc = $term ? get_term_meta( $term->term_id, '_msh_description', true ) : '';
        }

        if ( $desc ) {
            echo '<meta name="description" content="' . esc_attr( $this->parse_variables( $desc ) ) . '" />' . "\n";
        }
    }

    private function output_robots(): void {
        if ( ! is_singular() ) return;

        $robots = get_post_meta( get_the_ID(), '_msh_robots', true );
        if ( ! $robots || ! is_array( $robots ) ) return;

        $directives = [];
        if ( in_array( 'noindex', $robots, true ) ) $directives[] = 'noindex';
        if ( in_array( 'nofollow', $robots, true ) ) $directives[] = 'nofollow';
        if ( in_array( 'noarchive', $robots, true ) ) $directives[] = 'noarchive';
        if ( in_array( 'noimageindex', $robots, true ) ) $directives[] = 'noimageindex';
        if ( in_array( 'nosnippet', $robots, true ) ) $directives[] = 'nosnippet';

        if ( ! empty( $directives ) ) {
            echo '<meta name="robots" content="' . esc_attr( implode( ', ', $directives ) ) . '" />' . "\n";
        }
    }

    private function output_canonical(): void {
        if ( ! is_singular() ) return;

        $canonical = get_post_meta( get_the_ID(), '_msh_canonical_url', true );
        if ( ! $canonical ) {
            $canonical = get_permalink();
        }

        echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
    }

    private function output_open_graph(): void {
        if ( ! is_singular() ) return;

        $post_id = get_the_ID();
        $title   = get_post_meta( $post_id, '_msh_og_title', true ) ?: get_the_title();
        $desc    = get_post_meta( $post_id, '_msh_og_description', true ) ?: get_post_meta( $post_id, '_msh_description', true );
        $image   = get_post_meta( $post_id, '_msh_og_image', true ) ?: get_the_post_thumbnail_url( $post_id, 'large' );
        $type    = is_front_page() ? 'website' : 'article';

        echo '<meta property="og:type" content="' . esc_attr( $type ) . '" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url( get_permalink() ) . '" />' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
        echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '" />' . "\n";

        if ( $desc ) {
            echo '<meta property="og:description" content="' . esc_attr( $desc ) . '" />' . "\n";
        }
        if ( $image ) {
            echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
        }

        if ( $type === 'article' ) {
            echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c' ) ) . '" />' . "\n";
            echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c' ) ) . '" />' . "\n";
        }
    }

    private function output_twitter_card(): void {
        if ( ! is_singular() ) return;

        $post_id = get_the_ID();
        $title   = get_post_meta( $post_id, '_msh_twitter_title', true ) ?: get_the_title();
        $desc    = get_post_meta( $post_id, '_msh_twitter_description', true ) ?: get_post_meta( $post_id, '_msh_description', true );
        $image   = get_post_meta( $post_id, '_msh_twitter_image', true ) ?: get_the_post_thumbnail_url( $post_id, 'large' );

        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";

        if ( $desc ) {
            echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '" />' . "\n";
        }
        if ( $image ) {
            echo '<meta name="twitter:image" content="' . esc_url( $image ) . '" />' . "\n";
        }
    }

    public function parse_variables( string $text, ?\WP_Post $post = null, ?\WP_Term $term = null ): string {
        if ( ! $post && is_singular() ) {
            $post = get_post();
        }

        $vars = [
            '%title%'       => $post ? $post->post_title : ( $term ? $term->name : '' ),
            '%sitename%'    => get_bloginfo( 'name' ),
            '%tagline%'     => get_bloginfo( 'description' ),
            '%sep%'         => $this->filter_separator( '-' ),
            '%date%'        => $post ? get_the_date( '', $post ) : '',
            '%modified%'    => $post ? get_the_modified_date( '', $post ) : '',
            '%author%'      => $post ? get_the_author_meta( 'display_name', $post->post_author ) : '',
            '%excerpt%'     => $post ? wp_trim_words( $post->post_excerpt ?: $post->post_content, 20 ) : '',
            '%category%'    => '',
            '%term_title%'  => $term ? $term->name : '',
            '%term_desc%'   => $term ? $term->description : '',
            '%page%'        => '',
            '%currentyear%' => date( 'Y' ),
        ];

        if ( $post ) {
            $cats = get_the_category( $post->ID );
            $vars['%category%'] = ! empty( $cats ) ? $cats[0]->name : '';
        }

        $paged = get_query_var( 'paged', 0 );
        if ( $paged > 1 ) {
            $vars['%page%'] = sprintf( 'Page %d', $paged );
        }

        return str_replace( array_keys( $vars ), array_values( $vars ), $text );
    }
}
