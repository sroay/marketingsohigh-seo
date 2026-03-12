<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MSH_Module_WooCommerce {

    public function __construct() {
        if ( ! class_exists( 'WooCommerce' ) ) return;

        add_filter( 'msh_schema_graph', [ $this, 'add_product_schema' ] );
        add_action( 'woocommerce_product_options_inventory_product_data', [ $this, 'add_identifier_fields' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_identifier_fields' ] );
    }

    public function add_product_schema( array $output ): array {
        if ( ! is_singular( 'product' ) ) return $output;

        $product = wc_get_product( get_the_ID() );
        if ( ! $product ) return $output;

        $schema = [
            '@type'       => 'Product',
            '@id'         => get_permalink() . '#product',
            'name'        => $product->get_name(),
            'description' => wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ),
            'url'         => get_permalink(),
            'sku'         => $product->get_sku(),
        ];

        // Image
        $image = wp_get_attachment_url( $product->get_image_id() );
        if ( $image ) {
            $schema['image'] = $image;
        }

        // Price
        $schema['offers'] = [
            '@type'           => 'Offer',
            'url'             => get_permalink(),
            'priceCurrency'   => get_woocommerce_currency(),
            'price'           => $product->get_price(),
            'availability'    => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'priceValidUntil' => date( 'Y-12-31' ),
        ];

        if ( $product->is_on_sale() ) {
            $schema['offers']['price'] = $product->get_sale_price();
        }

        // Brand
        $brand = get_post_meta( $product->get_id(), '_msh_brand', true );
        if ( $brand ) {
            $schema['brand'] = [ '@type' => 'Brand', 'name' => $brand ];
        }

        // Identifiers
        $gtin = get_post_meta( $product->get_id(), '_msh_gtin', true );
        if ( $gtin ) $schema['gtin'] = $gtin;

        $mpn = get_post_meta( $product->get_id(), '_msh_mpn', true );
        if ( $mpn ) $schema['mpn'] = $mpn;

        $isbn = get_post_meta( $product->get_id(), '_msh_isbn', true );
        if ( $isbn ) $schema['isbn'] = $isbn;

        // Reviews
        if ( $product->get_review_count() > 0 ) {
            $schema['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => $product->get_average_rating(),
                'reviewCount' => $product->get_review_count(),
            ];
        }

        $output['@graph'][] = $schema;
        return $output;
    }

    public function add_identifier_fields(): void {
        echo '<div class="options_group">';
        echo '<p class="form-field"><strong>MarketingSoHigh SEO</strong></p>';

        woocommerce_wp_text_input( [
            'id'    => '_msh_gtin',
            'label' => 'GTIN / UPC / EAN',
            'desc_tip' => true,
            'description' => 'Global Trade Item Number for rich snippets',
        ] );
        woocommerce_wp_text_input( [
            'id'    => '_msh_mpn',
            'label' => 'MPN',
            'desc_tip' => true,
            'description' => 'Manufacturer Part Number',
        ] );
        woocommerce_wp_text_input( [
            'id'    => '_msh_isbn',
            'label' => 'ISBN',
            'desc_tip' => true,
            'description' => 'International Standard Book Number',
        ] );
        woocommerce_wp_text_input( [
            'id'    => '_msh_brand',
            'label' => 'Brand',
            'desc_tip' => true,
            'description' => 'Product brand name',
        ] );

        echo '</div>';
    }

    public function save_identifier_fields( int $post_id ): void {
        foreach ( [ '_msh_gtin', '_msh_mpn', '_msh_isbn', '_msh_brand' ] as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_post_meta( $post_id, $field, sanitize_text_field( $_POST[ $field ] ) );
            }
        }
    }
}
