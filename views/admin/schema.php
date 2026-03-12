<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap msh-wrap">
    <h1>Schema / Structured Data</h1>
    <p>MarketingSoHigh SEO automatically generates JSON-LD schema for your content.</p>

    <h2>Default Schema Types</h2>
    <table class="widefat fixed striped">
        <thead><tr><th>Post Type</th><th>Default Schema</th></tr></thead>
        <tbody>
            <tr><td>Posts</td><td>Article</td></tr>
            <tr><td>Pages</td><td>WebPage</td></tr>
            <tr><td>Products (WooCommerce)</td><td>Product</td></tr>
        </tbody>
    </table>

    <h2>Available Schema Types (<?php echo count( MSH_Module_Schema::get_available_types() ); ?>)</h2>
    <div style="columns:3;column-gap:20px;margin-top:12px">
        <?php foreach ( MSH_Module_Schema::get_available_types() as $value => $label ) : ?>
            <div style="padding:4px 0"><code><?php echo esc_html( $value ); ?></code> — <?php echo esc_html( $label ); ?></div>
        <?php endforeach; ?>
    </div>

    <h2>Global Schema</h2>
    <p>The following schema is automatically output on every page:</p>
    <ul>
        <li>✓ <strong>WebSite</strong> with Sitelinks Search Box</li>
        <li>✓ <strong>Organization</strong> (from Local SEO settings)</li>
        <li>✓ <strong>BreadcrumbList</strong> on all inner pages</li>
        <li>✓ <strong>WebPage</strong> on all singular pages</li>
    </ul>
</div>
