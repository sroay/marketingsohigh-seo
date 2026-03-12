<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Frontend orchestrator — removes conflicting SEO output from other plugins/themes.
 */
class MSH_Frontend {

    public function __construct() {
        // Remove default WordPress canonical
        remove_action( 'wp_head', 'rel_canonical' );

        // Remove WP default meta description if any theme adds one
        add_action( 'wp_head', [ $this, 'cleanup_head' ], 0 );

        // Remove Yoast output if still active (migration scenario)
        add_action( 'template_redirect', [ $this, 'disable_conflicting_plugins' ], 0 );
    }

    public function cleanup_head(): void {
        // Remove WordPress default generator tag (optional, minimal security benefit)
        remove_action( 'wp_head', 'wp_generator' );

        // Remove shortlinks
        remove_action( 'wp_head', 'wp_shortlink_wp_head' );
    }

    public function disable_conflicting_plugins(): void {
        // Disable Yoast frontend output if Yoast is still active
        if ( defined( 'WPSEO_VERSION' ) ) {
            // Remove Yoast OpenGraph
            if ( isset( $GLOBALS['wpseo_og'] ) ) {
                remove_action( 'wpseo_head', [ $GLOBALS['wpseo_og'], 'opengraph' ], 30 );
            }
        }

        // Disable RankMath frontend output if RankMath is still active
        if ( class_exists( 'RankMath' ) ) {
            // RankMath uses its own head action
            remove_all_actions( 'rank_math/head' );
        }
    }
}
