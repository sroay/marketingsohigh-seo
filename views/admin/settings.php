<?php if ( ! defined( 'ABSPATH' ) ) exit;
$settings = get_option( 'msh_general_settings', [] );
$modules = get_option( 'msh_modules', [] );
$all_modules = [
    'meta' => 'Meta Tags & SEO Titles',
    'schema' => 'Schema / JSON-LD',
    'sitemap' => 'XML Sitemaps',
    'redirects' => 'Redirections',
    'monitor404' => '404 Monitor',
    'image_seo' => 'Image SEO',
    'breadcrumbs' => 'Breadcrumbs',
    'internal_links' => 'Internal Links',
    'local_seo' => 'Local SEO',
    'woocommerce' => 'WooCommerce SEO',
    'indexnow' => 'IndexNow',
    'connector' => 'MSH Connector',
];
?>
<div class="wrap msh-wrap">
    <h1>General Settings</h1>
    <form method="post" action="options.php">
        <?php settings_fields( 'msh_general' ); ?>

        <h2>Modules</h2>
        <table class="form-table">
            <?php foreach ( $all_modules as $slug => $label ) : ?>
            <tr>
                <th><?php echo esc_html( $label ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="msh_modules[]" value="<?php echo esc_attr( $slug ); ?>"
                            <?php checked( in_array( $slug, $modules ) ); ?> />
                        Enable
                    </label>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <h2>General</h2>
        <table class="form-table">
            <tr>
                <th>Title Separator</th>
                <td><input type="text" name="msh_general_settings[separator]" value="<?php echo esc_attr( $settings['separator'] ?? '-' ); ?>" class="small-text" /></td>
            </tr>
            <tr>
                <th>Default Title Format</th>
                <td><input type="text" name="msh_general_settings[title_format]" value="<?php echo esc_attr( $settings['title_format'] ?? '%title% %sep% %sitename%' ); ?>" class="regular-text" />
                <p class="description">Variables: %title%, %sitename%, %tagline%, %sep%, %date%, %author%, %category%, %currentyear%</p></td>
            </tr>
            <tr>
                <th>Noindex Empty Taxonomies</th>
                <td><label><input type="checkbox" name="msh_general_settings[noindex_empty_tax]" value="1" <?php checked( $settings['noindex_empty_tax'] ?? false ); ?> /> Add noindex to empty category/tag pages</label></td>
            </tr>
        </table>

        <h2>Image SEO</h2>
        <table class="form-table">
            <tr>
                <th>Auto ALT Template</th>
                <td><input type="text" name="msh_general_settings[image_alt_template]" value="<?php echo esc_attr( $settings['image_alt_template'] ?? '%filename%' ); ?>" class="regular-text" />
                <p class="description">Variables: %filename%, %title%, %name%</p></td>
            </tr>
            <tr>
                <th>Auto Title Template</th>
                <td><input type="text" name="msh_general_settings[image_title_template]" value="<?php echo esc_attr( $settings['image_title_template'] ?? '%filename%' ); ?>" class="regular-text" /></td>
            </tr>
        </table>

        <h2>Breadcrumbs</h2>
        <table class="form-table">
            <tr>
                <th>Separator</th>
                <td><input type="text" name="msh_general_settings[breadcrumb_separator]" value="<?php echo esc_attr( $settings['breadcrumb_separator'] ?? ' &raquo; ' ); ?>" class="small-text" /></td>
            </tr>
            <tr>
                <th>Home Label</th>
                <td><input type="text" name="msh_general_settings[breadcrumb_home]" value="<?php echo esc_attr( $settings['breadcrumb_home'] ?? 'Home' ); ?>" class="regular-text" /></td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>
