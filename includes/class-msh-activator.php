<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MSH_Activator {
    public static function activate(): void {
        self::create_tables();
        self::set_defaults();
        self::generate_indexnow_key();
        flush_rewrite_rules();
    }

    private static function create_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $prefix  = $wpdb->prefix . 'msh_';

        $sql = "
        CREATE TABLE {$prefix}redirects (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source varchar(500) NOT NULL,
            destination varchar(500) NOT NULL DEFAULT '',
            type smallint(4) NOT NULL DEFAULT 301,
            match_mode varchar(20) NOT NULL DEFAULT 'exact',
            hits bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY source_idx (source(191))
        ) $charset;

        CREATE TABLE {$prefix}redirects_cache (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            from_url varchar(500) NOT NULL,
            redirect_id bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY from_url_idx (from_url(191))
        ) $charset;

        CREATE TABLE {$prefix}404_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            url varchar(500) NOT NULL,
            referrer varchar(500) NOT NULL DEFAULT '',
            user_agent varchar(500) NOT NULL DEFAULT '',
            ip varchar(45) NOT NULL DEFAULT '',
            hits int(11) unsigned NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY url_idx (url(191))
        ) $charset;

        CREATE TABLE {$prefix}internal_links (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            target_id bigint(20) unsigned NOT NULL DEFAULT 0,
            target_url varchar(500) NOT NULL DEFAULT '',
            anchor varchar(500) NOT NULL DEFAULT '',
            type varchar(10) NOT NULL DEFAULT 'internal',
            is_nofollow tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY post_id_idx (post_id),
            KEY target_id_idx (target_id)
        ) $charset;

        CREATE TABLE {$prefix}internal_meta (
            post_id bigint(20) unsigned NOT NULL,
            internal_out int(11) unsigned NOT NULL DEFAULT 0,
            external_out int(11) unsigned NOT NULL DEFAULT 0,
            incoming int(11) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (post_id)
        ) $charset;

        CREATE TABLE {$prefix}analytics (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            date date NOT NULL,
            impressions int(11) unsigned NOT NULL DEFAULT 0,
            clicks int(11) unsigned NOT NULL DEFAULT 0,
            ctr decimal(5,2) NOT NULL DEFAULT 0,
            position decimal(5,1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY post_date (post_id, date)
        ) $charset;

        CREATE TABLE {$prefix}schema_templates (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            schema_type varchar(50) NOT NULL,
            schema_json longtext NOT NULL,
            conditions longtext NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;
        ";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        update_option( 'msh_db_version', MSH_DB_VERSION );
    }

    private static function set_defaults(): void {
        $defaults = [
            'msh_modules' => [
                'meta', 'schema', 'sitemap', 'redirects', 'monitor404',
                'image_seo', 'breadcrumbs', 'internal_links', 'indexnow',
            ],
            'msh_general_settings' => [
                'separator'         => '-',
                'title_format'      => '%title% %sep% %sitename%',
                'noindex_empty_tax' => true,
                'nofollow_external' => false,
                'open_external_new' => true,
            ],
            'msh_sitemap_settings' => [
                'max_urls'       => 1000,
                'include_images' => true,
                'post_types'     => [ 'post', 'page' ],
                'taxonomies'     => [ 'category', 'post_tag' ],
            ],
        ];

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                update_option( $key, $value );
            }
        }
    }

    private static function generate_indexnow_key(): void {
        if ( ! get_option( 'msh_indexnow_key' ) ) {
            update_option( 'msh_indexnow_key', str_replace( '-', '', wp_generate_uuid4() ) );
        }
    }
}
