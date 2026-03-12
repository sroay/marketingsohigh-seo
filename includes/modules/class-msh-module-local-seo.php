<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MSH_Module_Local_SEO {

    public function __construct() {
        add_action( 'wp_head', [ $this, 'output_local_schema' ], 3 );
        add_shortcode( 'msh_map', [ $this, 'map_shortcode' ] );
        add_shortcode( 'msh_hours', [ $this, 'hours_shortcode' ] );
    }

    public function output_local_schema(): void {
        if ( ! is_front_page() ) return;

        $settings = get_option( 'msh_local_seo_settings', [] );
        if ( empty( $settings['business_name'] ) ) return;

        // Schema is already output by MSH_Module_Schema::get_organization_schema()
        // This module adds opening hours if present
        if ( ! empty( $settings['opening_hours'] ) ) {
            $this->output_hours_schema( $settings );
        }
    }

    private function output_hours_schema( array $settings ): void {
        $hours = $settings['opening_hours'] ?? [];
        if ( empty( $hours ) ) return;

        $specs = [];
        $days = [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];

        foreach ( $days as $day ) {
            $key = strtolower( $day );
            if ( ! empty( $hours[ $key ] ) && ! empty( $hours[ $key ]['open'] ) ) {
                $specs[] = [
                    '@type'     => 'OpeningHoursSpecification',
                    'dayOfWeek' => $day,
                    'opens'     => $hours[ $key ]['open'],
                    'closes'    => $hours[ $key ]['close'],
                ];
            }
        }

        if ( empty( $specs ) ) return;

        // This is added via the msh_schema_graph filter in class-msh-module-schema.php
        add_filter( 'msh_schema_graph', function( array $output ) use ( $specs ) {
            foreach ( $output['@graph'] as &$item ) {
                if ( isset( $item['@type'] ) && ( $item['@type'] === 'LocalBusiness' || $item['@type'] === 'Organization' ) ) {
                    $item['openingHoursSpecification'] = $specs;
                }
            }
            return $output;
        } );
    }

    public function map_shortcode( array $atts = [] ): string {
        $settings = get_option( 'msh_local_seo_settings', [] );
        $lat = $settings['latitude'] ?? '';
        $lng = $settings['longitude'] ?? '';

        if ( ! $lat || ! $lng ) return '';

        $zoom   = $atts['zoom'] ?? 15;
        $width  = $atts['width'] ?? '100%';
        $height = $atts['height'] ?? '400px';

        return sprintf(
            '<iframe class="msh-map" src="https://maps.google.com/maps?q=%s,%s&z=%d&output=embed" width="%s" height="%s" style="border:0;" allowfullscreen loading="lazy"></iframe>',
            esc_attr( $lat ), esc_attr( $lng ), (int) $zoom, esc_attr( $width ), esc_attr( $height )
        );
    }

    public function hours_shortcode(): string {
        $settings = get_option( 'msh_local_seo_settings', [] );
        $hours = $settings['opening_hours'] ?? [];
        if ( empty( $hours ) ) return '';

        $html = '<table class="msh-opening-hours">';
        $days = [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];

        foreach ( $days as $day ) {
            $key = strtolower( $day );
            $html .= '<tr>';
            $html .= '<td>' . esc_html( $day ) . '</td>';
            if ( ! empty( $hours[ $key ] ) && ! empty( $hours[ $key ]['open'] ) ) {
                $html .= '<td>' . esc_html( $hours[ $key ]['open'] ) . ' - ' . esc_html( $hours[ $key ]['close'] ) . '</td>';
            } else {
                $html .= '<td>Closed</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</table>';
        return $html;
    }
}
