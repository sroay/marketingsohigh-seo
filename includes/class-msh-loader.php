<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MSH_Loader {
    private static ?MSH_Loader $instance = null;
    private array $modules = [];

    public static function get_instance(): MSH_Loader {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_modules();
        $this->init_hooks();
    }

    private function load_modules(): void {
        $available = [
            'meta'           => 'MSH_Module_Meta',
            'schema'         => 'MSH_Module_Schema',
            'sitemap'        => 'MSH_Module_Sitemap',
            'redirects'      => 'MSH_Module_Redirects',
            'monitor404'     => 'MSH_Module_404_Monitor',
            'image_seo'      => 'MSH_Module_Image_SEO',
            'breadcrumbs'    => 'MSH_Module_Breadcrumbs',
            'internal_links' => 'MSH_Module_Internal_Links',
            'local_seo'      => 'MSH_Module_Local_SEO',
            'woocommerce'    => 'MSH_Module_WooCommerce',
            'indexnow'       => 'MSH_Module_IndexNow',
            'connector'      => 'MSH_Module_Connector',
        ];

        $enabled = get_option( 'msh_modules', array_keys( $available ) );

        foreach ( $available as $slug => $class ) {
            if ( in_array( $slug, $enabled, true ) && class_exists( $class ) ) {
                $this->modules[ $slug ] = new $class();
            }
        }
    }

    private function init_hooks(): void {
        if ( is_admin() ) {
            new MSH_Admin();
        }
        new MSH_Frontend();
    }

    public function get_module( string $slug ) {
        return $this->modules[ $slug ] ?? null;
    }
}
