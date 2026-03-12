<?php if ( ! defined( 'ABSPATH' ) ) exit;
$settings = get_option( 'msh_sitemap_settings', [] );
?>
<div class="wrap msh-wrap">
    <h1>Sitemap Settings</h1>
    <p>Your sitemap index: <a href="<?php echo home_url( '/sitemap_index.xml' ); ?>" target="_blank"><?php echo home_url( '/sitemap_index.xml' ); ?></a></p>

    <form method="post" action="options.php">
        <?php settings_fields( 'msh_sitemap' ); ?>
        <table class="form-table">
            <tr>
                <th>Max URLs per Sitemap</th>
                <td><input type="number" name="msh_sitemap_settings[max_urls]" value="<?php echo (int) ( $settings['max_urls'] ?? 1000 ); ?>" min="100" max="50000" /></td>
            </tr>
            <tr>
                <th>Include Images</th>
                <td><label><input type="checkbox" name="msh_sitemap_settings[include_images]" value="1" <?php checked( $settings['include_images'] ?? true ); ?> /> Include image references in post sitemaps</label></td>
            </tr>
            <tr>
                <th>Post Types</th>
                <td>
                    <?php foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $pt ) : ?>
                        <label style="display:block;margin-bottom:4px">
                            <input type="checkbox" name="msh_sitemap_settings[post_types][]" value="<?php echo esc_attr( $pt->name ); ?>"
                                <?php checked( in_array( $pt->name, $settings['post_types'] ?? [ 'post', 'page' ] ) ); ?> />
                            <?php echo esc_html( $pt->labels->name ); ?>
                        </label>
                    <?php endforeach; ?>
                </td>
            </tr>
            <tr>
                <th>Taxonomies</th>
                <td>
                    <?php foreach ( get_taxonomies( [ 'public' => true ], 'objects' ) as $tax ) : ?>
                        <label style="display:block;margin-bottom:4px">
                            <input type="checkbox" name="msh_sitemap_settings[taxonomies][]" value="<?php echo esc_attr( $tax->name ); ?>"
                                <?php checked( in_array( $tax->name, $settings['taxonomies'] ?? [ 'category', 'post_tag' ] ) ); ?> />
                            <?php echo esc_html( $tax->labels->name ); ?>
                        </label>
                    <?php endforeach; ?>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>
