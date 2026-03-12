<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MSH_Autoloader {
    private array $paths = [];

    public function __construct() {
        $this->paths = [
            MSH_PLUGIN_DIR . 'includes/',
            MSH_PLUGIN_DIR . 'includes/modules/',
            MSH_PLUGIN_DIR . 'includes/admin/',
            MSH_PLUGIN_DIR . 'includes/frontend/',
        ];
        spl_autoload_register( [ $this, 'autoload' ] );
    }

    public function autoload( string $class ): void {
        if ( strpos( $class, 'MSH_' ) !== 0 ) {
            return;
        }
        $file = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
        foreach ( $this->paths as $path ) {
            $full = $path . $file;
            if ( file_exists( $full ) ) {
                require_once $full;
                return;
            }
        }
    }
}
