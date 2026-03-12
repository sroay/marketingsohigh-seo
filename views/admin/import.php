<?php if ( ! defined( 'ABSPATH' ) ) exit;

// Detect installed plugins
$has_yoast = defined( 'WPSEO_VERSION' );
$has_rankmath = class_exists( 'RankMath' );
$has_aioseo = function_exists( 'aioseo' );
$has_seopress = defined( 'SEOPRESS_VERSION' );
?>
<div class="wrap msh-wrap">
    <h1>Import / Export</h1>

    <h2>Import from Other Plugins</h2>
    <?php if ( $has_yoast || $has_rankmath || $has_aioseo || $has_seopress ) : ?>
        <div class="msh-import-cards">
            <?php if ( $has_yoast ) : ?>
                <div class="msh-card">
                    <h3>Yoast SEO <?php echo esc_html( WPSEO_VERSION ); ?></h3>
                    <p>Import SEO titles, descriptions, focus keywords, Open Graph, robots meta, and redirects.</p>
                    <form method="post">
                        <?php wp_nonce_field( 'msh_import', 'msh_import_nonce' ); ?>
                        <input type="hidden" name="msh_import_source" value="yoast" />
                        <button type="submit" class="button button-primary">Import from Yoast</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ( $has_rankmath ) : ?>
                <div class="msh-card">
                    <h3>RankMath SEO</h3>
                    <p>Import SEO titles, descriptions, focus keywords, Open Graph, schema, robots meta, and redirects.</p>
                    <form method="post">
                        <?php wp_nonce_field( 'msh_import', 'msh_import_nonce' ); ?>
                        <input type="hidden" name="msh_import_source" value="rankmath" />
                        <button type="submit" class="button button-primary">Import from RankMath</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ( $has_aioseo ) : ?>
                <div class="msh-card">
                    <h3>All in One SEO</h3>
                    <form method="post">
                        <?php wp_nonce_field( 'msh_import', 'msh_import_nonce' ); ?>
                        <input type="hidden" name="msh_import_source" value="aioseo" />
                        <button type="submit" class="button button-primary">Import from AIOSEO</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <p>No compatible SEO plugins detected. You can start fresh!</p>
    <?php endif; ?>

    <h2>Export</h2>
    <p>Export all MarketingSoHigh SEO settings and meta data.</p>
    <form method="post">
        <?php wp_nonce_field( 'msh_export', 'msh_export_nonce' ); ?>
        <input type="hidden" name="msh_action" value="export" />
        <button type="submit" class="button">Export Settings</button>
    </form>
</div>
