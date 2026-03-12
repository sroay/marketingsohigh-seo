<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MSH_Admin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_metabox' ] );
        add_action( 'save_post', [ $this, 'save_metabox' ], 10, 2 );
        add_action( 'wp_ajax_msh_calculate_score', [ $this, 'ajax_calculate_score' ] );

        // Admin columns
        add_filter( 'manage_posts_columns', [ $this, 'add_seo_columns' ] );
        add_action( 'manage_posts_custom_column', [ $this, 'render_seo_column' ], 10, 2 );
        add_filter( 'manage_pages_columns', [ $this, 'add_seo_columns' ] );
        add_action( 'manage_pages_custom_column', [ $this, 'render_seo_column' ], 10, 2 );

        // Plugin action links
        add_filter( 'plugin_action_links_' . MSH_PLUGIN_BASENAME, [ $this, 'plugin_links' ] );
    }

    public function register_menus(): void {
        add_menu_page(
            'MarketingSoHigh SEO',
            'MSH SEO',
            'manage_options',
            'msh-seo',
            [ $this, 'page_dashboard' ],
            'dashicons-chart-area',
            80
        );

        add_submenu_page( 'msh-seo', 'Dashboard', 'Dashboard', 'manage_options', 'msh-seo', [ $this, 'page_dashboard' ] );
        add_submenu_page( 'msh-seo', 'General Settings', 'General', 'manage_options', 'msh-settings', [ $this, 'page_settings' ] );
        add_submenu_page( 'msh-seo', 'Titles & Meta', 'Titles & Meta', 'manage_options', 'msh-titles', [ $this, 'page_titles' ] );
        add_submenu_page( 'msh-seo', 'Sitemaps', 'Sitemaps', 'manage_options', 'msh-sitemaps', [ $this, 'page_sitemaps' ] );
        add_submenu_page( 'msh-seo', 'Redirections', 'Redirections', 'manage_options', 'msh-redirects', [ $this, 'page_redirects' ] );
        add_submenu_page( 'msh-seo', '404 Monitor', '404 Monitor', 'manage_options', 'msh-404', [ $this, 'page_404' ] );
        add_submenu_page( 'msh-seo', 'Schema', 'Schema', 'manage_options', 'msh-schema', [ $this, 'page_schema' ] );
        add_submenu_page( 'msh-seo', 'Local SEO', 'Local SEO', 'manage_options', 'msh-local', [ $this, 'page_local' ] );
        add_submenu_page( 'msh-seo', 'MSH Connector', 'MSH Connector', 'manage_options', 'msh-connector', [ $this, 'page_connector' ] );
        add_submenu_page( 'msh-seo', 'Import / Export', 'Import / Export', 'manage_options', 'msh-import', [ $this, 'page_import' ] );
    }

    public function register_settings(): void {
        register_setting( 'msh_general', 'msh_general_settings' );
        register_setting( 'msh_general', 'msh_modules' );
        register_setting( 'msh_sitemap', 'msh_sitemap_settings' );
        register_setting( 'msh_local', 'msh_local_seo_settings' );
        register_setting( 'msh_connector', 'msh_connector_api_key' );
    }

    public function enqueue_assets( string $hook ): void {
        // Admin pages
        if ( strpos( $hook, 'msh-' ) !== false || $hook === 'post.php' || $hook === 'post-new.php' ) {
            wp_enqueue_style( 'msh-admin', MSH_PLUGIN_URL . 'assets/css/admin.css', [], MSH_VERSION );
            wp_enqueue_script( 'msh-admin', MSH_PLUGIN_URL . 'assets/js/admin.js', [ 'jquery' ], MSH_VERSION, true );
            wp_localize_script( 'msh-admin', 'mshAdmin', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'msh_admin_nonce' ),
            ] );
        }
    }

    public function add_metabox(): void {
        $post_types = get_post_types( [ 'public' => true ] );
        foreach ( $post_types as $pt ) {
            add_meta_box(
                'msh-seo-metabox',
                '🚀 MarketingSoHigh SEO',
                [ $this, 'render_metabox' ],
                $pt,
                'normal',
                'high'
            );
        }
    }

    public function render_metabox( \WP_Post $post ): void {
        wp_nonce_field( 'msh_save_meta', 'msh_meta_nonce' );

        $title   = get_post_meta( $post->ID, '_msh_title', true );
        $desc    = get_post_meta( $post->ID, '_msh_description', true );
        $keyword = get_post_meta( $post->ID, '_msh_focus_keyword', true );
        $score   = get_post_meta( $post->ID, '_msh_seo_score', true );
        $robots  = get_post_meta( $post->ID, '_msh_robots', true ) ?: [];
        $canonical = get_post_meta( $post->ID, '_msh_canonical_url', true );
        $og_title  = get_post_meta( $post->ID, '_msh_og_title', true );
        $og_desc   = get_post_meta( $post->ID, '_msh_og_description', true );
        $og_image  = get_post_meta( $post->ID, '_msh_og_image', true );
        $schema_type = get_post_meta( $post->ID, '_msh_schema_type', true );
        $breadcrumb  = get_post_meta( $post->ID, '_msh_breadcrumb_title', true );

        $score_class = 'msh-score-poor';
        if ( $score >= 80 ) $score_class = 'msh-score-good';
        elseif ( $score >= 50 ) $score_class = 'msh-score-ok';

        ?>
        <div class="msh-metabox">
            <div class="msh-tabs">
                <button type="button" class="msh-tab active" data-tab="general">General</button>
                <button type="button" class="msh-tab" data-tab="advanced">Advanced</button>
                <button type="button" class="msh-tab" data-tab="social">Social</button>
                <button type="button" class="msh-tab" data-tab="schema">Schema</button>
                <?php if ( $score ) : ?>
                    <span class="msh-score-badge <?php echo esc_attr( $score_class ); ?>"><?php echo (int) $score; ?>/100</span>
                <?php endif; ?>
            </div>

            <!-- General Tab -->
            <div class="msh-tab-content active" data-tab="general">
                <div class="msh-field">
                    <label for="msh_focus_keyword">Focus Keyword</label>
                    <input type="text" id="msh_focus_keyword" name="msh_focus_keyword" value="<?php echo esc_attr( $keyword ); ?>" placeholder="Enter your focus keyword" />
                </div>
                <div class="msh-field">
                    <label for="msh_title">SEO Title <span class="msh-char-count" data-target="msh_title" data-max="60"></span></label>
                    <input type="text" id="msh_title" name="msh_title" value="<?php echo esc_attr( $title ); ?>" placeholder="%title% %sep% %sitename%" />
                </div>
                <div class="msh-field">
                    <label for="msh_description">Meta Description <span class="msh-char-count" data-target="msh_description" data-max="160"></span></label>
                    <textarea id="msh_description" name="msh_description" rows="3" placeholder="Enter meta description..."><?php echo esc_textarea( $desc ); ?></textarea>
                </div>

                <!-- SERP Preview -->
                <div class="msh-serp-preview">
                    <div class="msh-serp-title"><?php echo esc_html( $title ?: $post->post_title ); ?></div>
                    <div class="msh-serp-url"><?php echo esc_url( get_permalink( $post ) ); ?></div>
                    <div class="msh-serp-desc"><?php echo esc_html( $desc ?: wp_trim_words( $post->post_content, 20 ) ); ?></div>
                </div>

                <div class="msh-score-panel" id="msh-score-panel">
                    <button type="button" class="button" id="msh-analyze-btn">Analyze SEO</button>
                    <div id="msh-score-results"></div>
                </div>
            </div>

            <!-- Advanced Tab -->
            <div class="msh-tab-content" data-tab="advanced">
                <div class="msh-field">
                    <label>Robots Meta</label>
                    <label><input type="checkbox" name="msh_robots[]" value="noindex" <?php checked( in_array( 'noindex', (array) $robots ) ); ?> /> No Index</label>
                    <label><input type="checkbox" name="msh_robots[]" value="nofollow" <?php checked( in_array( 'nofollow', (array) $robots ) ); ?> /> No Follow</label>
                    <label><input type="checkbox" name="msh_robots[]" value="noarchive" <?php checked( in_array( 'noarchive', (array) $robots ) ); ?> /> No Archive</label>
                    <label><input type="checkbox" name="msh_robots[]" value="noimageindex" <?php checked( in_array( 'noimageindex', (array) $robots ) ); ?> /> No Image Index</label>
                </div>
                <div class="msh-field">
                    <label for="msh_canonical_url">Canonical URL</label>
                    <input type="url" id="msh_canonical_url" name="msh_canonical_url" value="<?php echo esc_url( $canonical ); ?>" placeholder="Leave empty for default" />
                </div>
                <div class="msh-field">
                    <label for="msh_breadcrumb_title">Breadcrumb Title</label>
                    <input type="text" id="msh_breadcrumb_title" name="msh_breadcrumb_title" value="<?php echo esc_attr( $breadcrumb ); ?>" placeholder="Override breadcrumb label" />
                </div>
            </div>

            <!-- Social Tab -->
            <div class="msh-tab-content" data-tab="social">
                <h4>Facebook / Open Graph</h4>
                <div class="msh-field">
                    <label for="msh_og_title">OG Title</label>
                    <input type="text" id="msh_og_title" name="msh_og_title" value="<?php echo esc_attr( $og_title ); ?>" />
                </div>
                <div class="msh-field">
                    <label for="msh_og_description">OG Description</label>
                    <textarea id="msh_og_description" name="msh_og_description" rows="2"><?php echo esc_textarea( $og_desc ); ?></textarea>
                </div>
                <div class="msh-field">
                    <label for="msh_og_image">OG Image URL</label>
                    <input type="url" id="msh_og_image" name="msh_og_image" value="<?php echo esc_url( $og_image ); ?>" />
                </div>
            </div>

            <!-- Schema Tab -->
            <div class="msh-tab-content" data-tab="schema">
                <div class="msh-field">
                    <label for="msh_schema_type">Schema Type</label>
                    <select id="msh_schema_type" name="msh_schema_type">
                        <option value="">Auto-detect</option>
                        <?php foreach ( MSH_Module_Schema::get_available_types() as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $schema_type, $value ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <?php
    }

    public function save_metabox( int $post_id, \WP_Post $post ): void {
        if ( ! isset( $_POST['msh_meta_nonce'] ) || ! wp_verify_nonce( $_POST['msh_meta_nonce'], 'msh_save_meta' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $text_fields = [ 'msh_title' => '_msh_title', 'msh_description' => '_msh_description', 'msh_focus_keyword' => '_msh_focus_keyword', 'msh_canonical_url' => '_msh_canonical_url', 'msh_og_title' => '_msh_og_title', 'msh_og_description' => '_msh_og_description', 'msh_og_image' => '_msh_og_image', 'msh_breadcrumb_title' => '_msh_breadcrumb_title', 'msh_schema_type' => '_msh_schema_type' ];

        foreach ( $text_fields as $field => $meta_key ) {
            if ( isset( $_POST[ $field ] ) ) {
                $value = sanitize_text_field( $_POST[ $field ] );
                if ( $value ) {
                    update_post_meta( $post_id, $meta_key, $value );
                } else {
                    delete_post_meta( $post_id, $meta_key );
                }
            }
        }

        // Robots
        $robots = isset( $_POST['msh_robots'] ) ? array_map( 'sanitize_text_field', (array) $_POST['msh_robots'] ) : [];
        if ( ! empty( $robots ) ) {
            update_post_meta( $post_id, '_msh_robots', $robots );
        } else {
            delete_post_meta( $post_id, '_msh_robots' );
        }

        // Recalculate score
        MSH_Module_Meta_Scoring::calculate( $post_id );
    }

    public function ajax_calculate_score(): void {
        check_ajax_referer( 'msh_admin_nonce', 'nonce' );
        $post_id = (int) ( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) wp_send_json_error( 'Invalid post ID' );

        $result = MSH_Module_Meta_Scoring::calculate( $post_id );
        wp_send_json_success( $result );
    }

    public function add_seo_columns( array $columns ): array {
        $columns['msh_score'] = 'SEO';
        return $columns;
    }

    public function render_seo_column( string $column, int $post_id ): void {
        if ( $column !== 'msh_score' ) return;
        $score = get_post_meta( $post_id, '_msh_seo_score', true );
        if ( ! $score ) { echo '<span style="color:#999">—</span>'; return; }
        $color = $score >= 80 ? '#00b894' : ( $score >= 50 ? '#fdcb6e' : '#d63031' );
        echo '<span style="background:' . esc_attr( $color ) . ';color:#fff;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;">' . (int) $score . '</span>';
    }

    public function plugin_links( array $links ): array {
        array_unshift( $links, '<a href="' . admin_url( 'admin.php?page=msh-settings' ) . '">Settings</a>' );
        return $links;
    }

    // Page renderers — each loads a template from views/
    public function page_dashboard(): void { $this->render_page( 'dashboard' ); }
    public function page_settings(): void { $this->render_page( 'settings' ); }
    public function page_titles(): void { $this->render_page( 'titles' ); }
    public function page_sitemaps(): void { $this->render_page( 'sitemaps' ); }
    public function page_redirects(): void { $this->render_page( 'redirects' ); }
    public function page_404(): void { $this->render_page( 'monitor-404' ); }
    public function page_schema(): void { $this->render_page( 'schema' ); }
    public function page_local(): void { $this->render_page( 'local-seo' ); }
    public function page_connector(): void { $this->render_page( 'connector' ); }
    public function page_import(): void { $this->render_page( 'import' ); }

    private function render_page( string $view ): void {
        $file = MSH_PLUGIN_DIR . "views/admin/{$view}.php";
        if ( file_exists( $file ) ) {
            include $file;
        } else {
            echo '<div class="wrap"><h1>MarketingSoHigh SEO</h1><p>View not found: ' . esc_html( $view ) . '</p></div>';
        }
    }
}
