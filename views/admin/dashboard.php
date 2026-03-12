<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap msh-wrap">
    <h1>🚀 MarketingSoHigh SEO</h1>

    <div class="msh-dashboard-grid">
        <div class="msh-card">
            <h3>Modules</h3>
            <p>Enable or disable SEO modules.</p>
            <a href="<?php echo admin_url( 'admin.php?page=msh-settings' ); ?>" class="button">Manage Modules</a>
        </div>

        <div class="msh-card">
            <h3>Sitemaps</h3>
            <p>Your sitemap: <a href="<?php echo home_url( '/sitemap_index.xml' ); ?>" target="_blank"><?php echo home_url( '/sitemap_index.xml' ); ?></a></p>
        </div>

        <div class="msh-card">
            <h3>Redirections</h3>
            <?php
            global $wpdb;
            $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}msh_redirects" );
            ?>
            <p><?php echo (int) $count; ?> active redirects</p>
            <a href="<?php echo admin_url( 'admin.php?page=msh-redirects' ); ?>" class="button">Manage</a>
        </div>

        <div class="msh-card">
            <h3>404 Monitor</h3>
            <?php $total_404 = MSH_Module_404_Monitor::get_total(); ?>
            <p><?php echo (int) $total_404; ?> logged 404 errors</p>
            <a href="<?php echo admin_url( 'admin.php?page=msh-404' ); ?>" class="button">View Logs</a>
        </div>

        <div class="msh-card">
            <h3>IndexNow</h3>
            <?php $key = get_option( 'msh_indexnow_key' ); ?>
            <p>API Key: <code><?php echo esc_html( $key ? substr( $key, 0, 8 ) . '...' : 'Not set' ); ?></code></p>
            <p>URLs are automatically submitted on publish/update.</p>
        </div>

        <div class="msh-card msh-card-highlight">
            <h3>⚡ MSH Connector</h3>
            <?php
            $api_key = get_option( 'msh_connector_api_key' );
            $last_sync = get_option( 'msh_connector_last_sync' );
            ?>
            <p>Status: <?php echo $api_key ? '<span style="color:#00b894">Connected</span>' : '<span style="color:#d63031">Not Connected</span>'; ?></p>
            <?php if ( $last_sync ) : ?>
                <p>Last sync: <?php echo esc_html( $last_sync ); ?></p>
            <?php endif; ?>
            <a href="<?php echo admin_url( 'admin.php?page=msh-connector' ); ?>" class="button button-primary">Configure</a>
        </div>
    </div>
</div>
