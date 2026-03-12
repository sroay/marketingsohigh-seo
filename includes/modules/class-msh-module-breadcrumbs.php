<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MSH_Module_Breadcrumbs {

    public function __construct() {
        add_shortcode( 'msh_breadcrumb', [ $this, 'shortcode' ] );
    }

    public function shortcode(): string {
        return $this->render();
    }

    public function render(): string {
        if ( is_front_page() ) return '';

        $settings  = get_option( 'msh_general_settings', [] );
        $separator = $settings['breadcrumb_separator'] ?? ' &raquo; ';
        $home_text = $settings['breadcrumb_home'] ?? 'Home';

        $crumbs = [];
        $crumbs[] = '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( $home_text ) . '</a>';

        if ( is_singular( 'post' ) ) {
            $cats = get_the_category();
            if ( ! empty( $cats ) ) {
                $cat = $cats[0];
                // Parent categories
                $parents = get_ancestors( $cat->term_id, 'category' );
                $parents = array_reverse( $parents );
                foreach ( $parents as $pid ) {
                    $parent = get_category( $pid );
                    $crumbs[] = '<a href="' . esc_url( get_category_link( $pid ) ) . '">' . esc_html( $parent->name ) . '</a>';
                }
                $crumbs[] = '<a href="' . esc_url( get_category_link( $cat ) ) . '">' . esc_html( $cat->name ) . '</a>';
            }
            $custom_title = get_post_meta( get_the_ID(), '_msh_breadcrumb_title', true );
            $crumbs[] = '<span class="msh-breadcrumb-current">' . esc_html( $custom_title ?: get_the_title() ) . '</span>';
        } elseif ( is_singular( 'page' ) ) {
            $ancestors = get_post_ancestors( get_the_ID() );
            $ancestors = array_reverse( $ancestors );
            foreach ( $ancestors as $aid ) {
                $crumbs[] = '<a href="' . esc_url( get_permalink( $aid ) ) . '">' . esc_html( get_the_title( $aid ) ) . '</a>';
            }
            $crumbs[] = '<span class="msh-breadcrumb-current">' . esc_html( get_the_title() ) . '</span>';
        } elseif ( is_category() || is_tag() || is_tax() ) {
            $term = get_queried_object();
            if ( $term ) {
                $crumbs[] = '<span class="msh-breadcrumb-current">' . esc_html( $term->name ) . '</span>';
            }
        } elseif ( is_search() ) {
            $crumbs[] = '<span class="msh-breadcrumb-current">Search: ' . esc_html( get_search_query() ) . '</span>';
        } elseif ( is_404() ) {
            $crumbs[] = '<span class="msh-breadcrumb-current">404 Not Found</span>';
        } elseif ( is_archive() ) {
            $crumbs[] = '<span class="msh-breadcrumb-current">' . esc_html( get_the_archive_title() ) . '</span>';
        }

        $html = '<nav class="msh-breadcrumbs" aria-label="Breadcrumb">';
        $html .= '<ol class="msh-breadcrumb-list" itemscope itemtype="https://schema.org/BreadcrumbList">';

        foreach ( $crumbs as $i => $crumb ) {
            $pos = $i + 1;
            $html .= '<li class="msh-breadcrumb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
            $html .= '<span itemprop="name">' . $crumb . '</span>';
            $html .= '<meta itemprop="position" content="' . $pos . '" />';
            if ( $i < count( $crumbs ) - 1 ) {
                $html .= '<span class="msh-breadcrumb-sep">' . $separator . '</span>';
            }
            $html .= '</li>';
        }

        $html .= '</ol></nav>';

        return $html;
    }
}

// Global helper function
function msh_breadcrumbs(): void {
    $loader = MSH_Loader::get_instance();
    $module = $loader->get_module( 'breadcrumbs' );
    if ( $module ) {
        echo $module->render();
    }
}

function msh_the_breadcrumbs(): void {
    msh_breadcrumbs();
}
