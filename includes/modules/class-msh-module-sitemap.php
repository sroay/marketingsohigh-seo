<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MSH_Module_Sitemap {

    public function __construct() {
        add_action( 'init', [ $this, 'register_rewrites' ] );
        add_action( 'template_redirect', [ $this, 'serve_sitemap' ] );
        add_filter( 'robots_txt', [ $this, 'add_sitemap_to_robots' ], 10, 2 );

        // Disable core WP sitemaps to avoid conflicts
        add_filter( 'wp_sitemaps_enabled', '__return_false' );
    }

    public function register_rewrites(): void {
        add_rewrite_rule( 'sitemap_index\.xml$', 'index.php?msh_sitemap=index', 'top' );
        add_rewrite_rule( 'sitemap-([a-z0-9_-]+?)-?(\d*)\.xml$', 'index.php?msh_sitemap=$matches[1]&msh_sitemap_page=$matches[2]', 'top' );
        add_rewrite_rule( 'sitemap\.xsl$', 'index.php?msh_sitemap=stylesheet', 'top' );

        add_rewrite_tag( '%msh_sitemap%', '([a-z0-9_-]+)' );
        add_rewrite_tag( '%msh_sitemap_page%', '(\d+)' );
    }

    public function serve_sitemap(): void {
        $type = get_query_var( 'msh_sitemap' );
        if ( ! $type ) return;

        if ( $type === 'stylesheet' ) {
            $this->serve_xsl();
            return;
        }

        $page = max( 1, (int) get_query_var( 'msh_sitemap_page', 1 ) );
        $settings = get_option( 'msh_sitemap_settings', [] );

        header( 'Content-Type: application/xml; charset=UTF-8' );
        header( 'X-Robots-Tag: noindex' );

        if ( $type === 'index' ) {
            echo $this->generate_index( $settings );
        } elseif ( $type === 'post' ) {
            echo $this->generate_post_type_sitemap( 'post', $page, $settings );
        } elseif ( $type === 'page' ) {
            echo $this->generate_post_type_sitemap( 'page', $page, $settings );
        } elseif ( $type === 'category' ) {
            echo $this->generate_taxonomy_sitemap( 'category', $page, $settings );
        } elseif ( $type === 'post_tag' ) {
            echo $this->generate_taxonomy_sitemap( 'post_tag', $page, $settings );
        } elseif ( post_type_exists( $type ) ) {
            echo $this->generate_post_type_sitemap( $type, $page, $settings );
        } elseif ( taxonomy_exists( $type ) ) {
            echo $this->generate_taxonomy_sitemap( $type, $page, $settings );
        } else {
            status_header( 404 );
            exit;
        }

        exit;
    }

    private function generate_index( array $settings ): string {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . home_url( '/sitemap.xsl' ) . '"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $post_types = $settings['post_types'] ?? [ 'post', 'page' ];
        foreach ( $post_types as $pt ) {
            $count = wp_count_posts( $pt );
            $total = isset( $count->publish ) ? (int) $count->publish : 0;
            $max   = $settings['max_urls'] ?? 1000;
            $pages = max( 1, ceil( $total / $max ) );

            for ( $i = 1; $i <= $pages; $i++ ) {
                $suffix = $pages > 1 ? $i : '';
                $xml .= '  <sitemap>' . "\n";
                $xml .= '    <loc>' . home_url( "/sitemap-{$pt}{$suffix}.xml" ) . '</loc>' . "\n";
                $xml .= '    <lastmod>' . date( 'c' ) . '</lastmod>' . "\n";
                $xml .= '  </sitemap>' . "\n";
            }
        }

        $taxonomies = $settings['taxonomies'] ?? [ 'category', 'post_tag' ];
        foreach ( $taxonomies as $tax ) {
            $xml .= '  <sitemap>' . "\n";
            $xml .= '    <loc>' . home_url( "/sitemap-{$tax}.xml" ) . '</loc>' . "\n";
            $xml .= '    <lastmod>' . date( 'c' ) . '</lastmod>' . "\n";
            $xml .= '  </sitemap>' . "\n";
        }

        $xml .= '</sitemapindex>';
        return $xml;
    }

    private function generate_post_type_sitemap( string $post_type, int $page, array $settings ): string {
        $max = $settings['max_urls'] ?? 1000;
        $include_images = $settings['include_images'] ?? true;

        $posts = get_posts( [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => $max,
            'offset'         => ( $page - 1 ) * $max,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'OR',
                [ 'key' => '_msh_robots', 'compare' => 'NOT EXISTS' ],
                [
                    'key'     => '_msh_robots',
                    'value'   => 'noindex',
                    'compare' => 'NOT LIKE',
                ],
            ],
        ] );

        $ns_image = $include_images ? ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"' : '';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . home_url( '/sitemap.xsl' ) . '"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . $ns_image . '>' . "\n";

        foreach ( $posts as $p ) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . esc_url( get_permalink( $p ) ) . '</loc>' . "\n";
            $xml .= '    <lastmod>' . get_the_modified_date( 'c', $p ) . '</lastmod>' . "\n";
            $xml .= '    <changefreq>' . $this->get_changefreq( $p ) . '</changefreq>' . "\n";
            $xml .= '    <priority>' . $this->get_priority( $p, $post_type ) . '</priority>' . "\n";

            if ( $include_images ) {
                $images = $this->extract_images( $p );
                foreach ( $images as $img ) {
                    $xml .= '    <image:image>' . "\n";
                    $xml .= '      <image:loc>' . esc_url( $img['url'] ) . '</image:loc>' . "\n";
                    if ( ! empty( $img['alt'] ) ) {
                        $xml .= '      <image:title>' . esc_xml( $img['alt'] ) . '</image:title>' . "\n";
                    }
                    $xml .= '    </image:image>' . "\n";
                }
            }

            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';
        return $xml;
    }

    private function generate_taxonomy_sitemap( string $taxonomy, int $page, array $settings ): string {
        $max = $settings['max_urls'] ?? 1000;

        $terms = get_terms( [
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'number'     => $max,
            'offset'     => ( $page - 1 ) * $max,
        ] );

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . home_url( '/sitemap.xsl' ) . '"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        if ( is_array( $terms ) ) {
            foreach ( $terms as $term ) {
                $noindex = get_term_meta( $term->term_id, '_msh_robots', true );
                if ( is_array( $noindex ) && in_array( 'noindex', $noindex, true ) ) continue;

                $xml .= '  <url>' . "\n";
                $xml .= '    <loc>' . esc_url( get_term_link( $term ) ) . '</loc>' . "\n";
                $xml .= '    <changefreq>weekly</changefreq>' . "\n";
                $xml .= '    <priority>0.4</priority>' . "\n";
                $xml .= '  </url>' . "\n";
            }
        }

        $xml .= '</urlset>';
        return $xml;
    }

    private function extract_images( \WP_Post $post ): array {
        $images = [];

        // Featured image
        $thumb = get_the_post_thumbnail_url( $post->ID, 'full' );
        if ( $thumb ) {
            $images[] = [ 'url' => $thumb, 'alt' => get_post_meta( get_post_thumbnail_id( $post->ID ), '_wp_attachment_image_alt', true ) ];
        }

        // Content images
        preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*(?:alt=["\']([^"\']*)["\'])?/i', $post->post_content, $matches, PREG_SET_ORDER );
        foreach ( $matches as $m ) {
            $images[] = [ 'url' => $m[1], 'alt' => $m[2] ?? '' ];
        }

        return array_slice( $images, 0, 50 ); // Max 50 images per URL
    }

    private function get_changefreq( \WP_Post $post ): string {
        $diff = time() - strtotime( $post->post_modified );
        if ( $diff < DAY_IN_SECONDS ) return 'daily';
        if ( $diff < WEEK_IN_SECONDS ) return 'weekly';
        if ( $diff < MONTH_IN_SECONDS ) return 'monthly';
        return 'yearly';
    }

    private function get_priority( \WP_Post $post, string $post_type ): string {
        if ( $post_type === 'page' && $post->ID === (int) get_option( 'page_on_front' ) ) return '1.0';
        if ( $post_type === 'page' ) return '0.6';
        return '0.7';
    }

    public function add_sitemap_to_robots( string $output, bool $public ): string {
        if ( $public ) {
            $output .= "\nSitemap: " . home_url( '/sitemap_index.xml' ) . "\n";
        }
        return $output;
    }

    private function serve_xsl(): void {
        header( 'Content-Type: text/xsl; charset=UTF-8' );
        echo '<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0"
    xmlns:html="http://www.w3.org/TR/REC-html40"
    xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
    xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes"/>
<xsl:template match="/">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>XML Sitemap — MarketingSoHigh SEO</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;margin:0;padding:20px;color:#333}h1{color:#1a1a1a;font-size:24px;margin-bottom:10px}p{color:#666;margin-bottom:20px}table{border-collapse:collapse;width:100%}th{background:#6c5ce7;color:#fff;text-align:left;padding:12px 16px;font-size:13px}td{padding:10px 16px;border-bottom:1px solid #eee;font-size:13px}tr:hover td{background:#f8f7ff}a{color:#6c5ce7;text-decoration:none}a:hover{text-decoration:underline}.badge{background:#6c5ce7;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px}</style>
</head>
<body>
    <h1>🚀 XML Sitemap</h1>
    <p>Generated by <strong>MarketingSoHigh SEO</strong></p>
    <xsl:choose>
        <xsl:when test="sitemap:sitemapindex">
            <table>
                <tr><th>Sitemap</th><th>Last Modified</th></tr>
                <xsl:for-each select="sitemap:sitemapindex/sitemap:sitemap">
                    <tr><td><a href="{sitemap:loc}"><xsl:value-of select="sitemap:loc"/></a></td><td><xsl:value-of select="sitemap:lastmod"/></td></tr>
                </xsl:for-each>
            </table>
        </xsl:when>
        <xsl:otherwise>
            <table>
                <tr><th>URL</th><th>Priority</th><th>Change Freq</th><th>Last Modified</th></tr>
                <xsl:for-each select="sitemap:urlset/sitemap:url">
                    <tr><td><a href="{sitemap:loc}"><xsl:value-of select="sitemap:loc"/></a></td><td><xsl:value-of select="sitemap:priority"/></td><td><xsl:value-of select="sitemap:changefreq"/></td><td><xsl:value-of select="sitemap:lastmod"/></td></tr>
                </xsl:for-each>
            </table>
        </xsl:otherwise>
    </xsl:choose>
</body>
</html>
</xsl:template>
</xsl:stylesheet>';
        exit;
    }
}
