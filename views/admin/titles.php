<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap msh-wrap">
    <h1>Titles & Meta</h1>
    <p>Configure default SEO title and description templates for each post type and taxonomy.</p>
    <p><em>Per-post overrides are available in the MSH SEO metabox on each post/page editor.</em></p>

    <h2>Post Types</h2>
    <table class="widefat fixed striped">
        <thead><tr><th>Post Type</th><th>Title Template</th><th>Description Template</th></tr></thead>
        <tbody>
        <?php foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $pt ) : ?>
            <tr>
                <td><strong><?php echo esc_html( $pt->labels->singular_name ); ?></strong></td>
                <td><code>%title% %sep% %sitename%</code></td>
                <td><code>%excerpt%</code></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Available Variables</h2>
    <p><code>%title%</code> <code>%sitename%</code> <code>%tagline%</code> <code>%sep%</code> <code>%date%</code> <code>%modified%</code> <code>%author%</code> <code>%excerpt%</code> <code>%category%</code> <code>%currentyear%</code> <code>%page%</code></p>
</div>
