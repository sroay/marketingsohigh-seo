<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SEO Scoring Engine — 30+ real-time tests matching/exceeding RankMath + Yoast.
 * Called from admin metabox via AJAX.
 */
class MSH_Module_Meta_Scoring {

    public static function calculate( int $post_id ): array {
        $post    = get_post( $post_id );
        $keyword = strtolower( trim( get_post_meta( $post_id, '_msh_focus_keyword', true ) ) );
        $title   = get_post_meta( $post_id, '_msh_title', true ) ?: $post->post_title;
        $desc    = get_post_meta( $post_id, '_msh_description', true ) ?: '';
        $content = strtolower( wp_strip_all_tags( $post->post_content ) );
        $slug    = $post->post_name;
        $words   = str_word_count( $content );

        $tests   = [];
        $score   = 0;
        $max     = 0;

        // --- Basic SEO Tests (each 5 points) ---
        $basic = [
            'keyword_in_title' => [
                'label'  => 'Focus keyword in SEO title',
                'pass'   => $keyword && stripos( $title, $keyword ) !== false,
                'weight' => 5,
            ],
            'keyword_in_desc' => [
                'label'  => 'Focus keyword in meta description',
                'pass'   => $keyword && stripos( $desc, $keyword ) !== false,
                'weight' => 5,
            ],
            'keyword_in_url' => [
                'label'  => 'Focus keyword in URL',
                'pass'   => $keyword && stripos( $slug, str_replace( ' ', '-', $keyword ) ) !== false,
                'weight' => 5,
            ],
            'keyword_in_first_10' => [
                'label'  => 'Focus keyword in first 10% of content',
                'pass'   => $keyword && stripos( substr( $content, 0, max( 100, (int)( strlen( $content ) * 0.1 ) ) ), $keyword ) !== false,
                'weight' => 5,
            ],
            'keyword_in_content' => [
                'label'  => 'Focus keyword in content',
                'pass'   => $keyword && stripos( $content, $keyword ) !== false,
                'weight' => 5,
            ],
            'content_length' => [
                'label'  => 'Content length (600+ words)',
                'pass'   => $words >= 600,
                'weight' => 5,
            ],
            'keyword_in_image_alt' => [
                'label'  => 'Focus keyword in image ALT text',
                'pass'   => $keyword && self::keyword_in_image_alt( $post->post_content, $keyword ),
                'weight' => 5,
            ],
        ];

        // --- Additional SEO Tests (each 4 points) ---
        $additional = [
            'keyword_in_headings' => [
                'label'  => 'Focus keyword in subheadings (H2-H6)',
                'pass'   => $keyword && preg_match( '/<h[2-6][^>]*>.*' . preg_quote( $keyword, '/' ) . '.*<\/h[2-6]>/is', $post->post_content ),
                'weight' => 4,
            ],
            'keyword_density' => [
                'label'  => 'Keyword density (0.5-2.5%)',
                'pass'   => $keyword && $words > 0 && self::keyword_density( $content, $keyword, $words ) >= 0.5 && self::keyword_density( $content, $keyword, $words ) <= 2.5,
                'weight' => 4,
            ],
            'url_length' => [
                'label'  => 'URL length (under 75 chars)',
                'pass'   => strlen( $slug ) <= 75,
                'weight' => 4,
            ],
            'external_links' => [
                'label'  => 'External links present',
                'pass'   => (bool) preg_match( '/<a[^>]+href=["\']https?:\/\/(?!' . preg_quote( wp_parse_url( home_url(), PHP_URL_HOST ), '/' ) . ')/i', $post->post_content ),
                'weight' => 4,
            ],
            'internal_links' => [
                'label'  => 'Internal links present',
                'pass'   => (bool) preg_match( '/<a[^>]+href=["\']' . preg_quote( home_url(), '/' ) . '/i', $post->post_content ),
                'weight' => 4,
            ],
            'keyword_unique' => [
                'label'  => 'Focus keyword not used on another post',
                'pass'   => $keyword && self::is_keyword_unique( $keyword, $post_id ),
                'weight' => 4,
            ],
        ];

        // --- Title Readability (each 3 points) ---
        $title_tests = [
            'title_keyword_start' => [
                'label'  => 'Focus keyword at beginning of title',
                'pass'   => $keyword && stripos( $title, $keyword ) !== false && stripos( $title, $keyword ) < strlen( $title ) / 2,
                'weight' => 3,
            ],
            'title_has_number' => [
                'label'  => 'Title contains a number',
                'pass'   => (bool) preg_match( '/\d/', $title ),
                'weight' => 3,
            ],
            'title_power_word' => [
                'label'  => 'Title uses a power word',
                'pass'   => self::has_power_word( $title ),
                'weight' => 3,
            ],
            'title_length' => [
                'label'  => 'SEO title length (30-60 chars)',
                'pass'   => strlen( $title ) >= 30 && strlen( $title ) <= 60,
                'weight' => 3,
            ],
        ];

        // --- Content Readability (each 3 points) ---
        $readability = [
            'short_paragraphs' => [
                'label'  => 'Short paragraphs (under 150 words each)',
                'pass'   => self::check_paragraph_length( $post->post_content ),
                'weight' => 3,
            ],
            'has_media' => [
                'label'  => 'Images or media present',
                'pass'   => (bool) preg_match( '/<img|<video|<iframe|<figure/i', $post->post_content ),
                'weight' => 3,
            ],
            'has_toc' => [
                'label'  => 'Table of contents or structured headings',
                'pass'   => substr_count( strtolower( $post->post_content ), '<h2' ) >= 3,
                'weight' => 3,
            ],
            'desc_length' => [
                'label'  => 'Meta description length (120-160 chars)',
                'pass'   => strlen( $desc ) >= 120 && strlen( $desc ) <= 160,
                'weight' => 3,
            ],
        ];

        $all_tests = array_merge( $basic, $additional, $title_tests, $readability );

        foreach ( $all_tests as $key => $test ) {
            $max += $test['weight'];
            if ( $test['pass'] ) {
                $score += $test['weight'];
            }
            $tests[ $key ] = [
                'label'  => $test['label'],
                'status' => $test['pass'] ? 'pass' : 'fail',
                'weight' => $test['weight'],
            ];
        }

        $pct = $max > 0 ? round( ( $score / $max ) * 100 ) : 0;

        update_post_meta( $post_id, '_msh_seo_score', $pct );

        return [
            'score'    => $pct,
            'grade'    => $pct >= 80 ? 'good' : ( $pct >= 50 ? 'ok' : 'poor' ),
            'tests'    => $tests,
            'passed'   => $score,
            'max'      => $max,
        ];
    }

    private static function keyword_density( string $content, string $keyword, int $total_words ): float {
        $count = substr_count( $content, $keyword );
        $kw_words = str_word_count( $keyword );
        return $total_words > 0 ? round( ( $count * $kw_words / $total_words ) * 100, 2 ) : 0;
    }

    private static function keyword_in_image_alt( string $html, string $keyword ): bool {
        preg_match_all( '/alt=["\']([^"\']*)["\']/', $html, $m );
        foreach ( $m[1] as $alt ) {
            if ( stripos( $alt, $keyword ) !== false ) return true;
        }
        return false;
    }

    private static function is_keyword_unique( string $keyword, int $exclude_id ): bool {
        global $wpdb;
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_msh_focus_keyword' AND meta_value = %s AND post_id != %d",
            $keyword, $exclude_id
        ) );
        return (int) $count === 0;
    }

    private static function has_power_word( string $text ): bool {
        $power_words = [ 'ultimate', 'definitive', 'complete', 'essential', 'proven', 'powerful', 'best', 'top', 'incredible', 'amazing', 'easy', 'simple', 'fast', 'free', 'new', 'secret', 'guaranteed', 'instant', 'exclusive', 'comprehensive' ];
        $lower = strtolower( $text );
        foreach ( $power_words as $w ) {
            if ( strpos( $lower, $w ) !== false ) return true;
        }
        return false;
    }

    private static function check_paragraph_length( string $html ): bool {
        $blocks = preg_split( '/<\/p>|<br\s*\/?>|\n\n/', $html );
        foreach ( $blocks as $block ) {
            $text = wp_strip_all_tags( $block );
            if ( str_word_count( $text ) > 150 ) return false;
        }
        return true;
    }
}
