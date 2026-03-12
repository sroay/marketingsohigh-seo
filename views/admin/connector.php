<?php if ( ! defined( 'ABSPATH' ) ) exit;
$api_key = get_option( 'msh_connector_api_key', '' );
$site_id = get_option( 'msh_connector_site_id', '' );
$last_sync = get_option( 'msh_connector_last_sync', '' );
?>
<div class="wrap msh-wrap">
    <h1>⚡ MSH Connector</h1>
    <p>Connect this WordPress site to your <strong>MarketingSoHigh</strong> dashboard for AI-powered content publishing, keyword syncing, and automated SEO.</p>

    <form method="post" action="options.php">
        <?php settings_fields( 'msh_connector' ); ?>

        <table class="form-table">
            <tr>
                <th>API Key</th>
                <td>
                    <input type="password" name="msh_connector_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" />
                    <p class="description">Get your API key from your <a href="https://marketingsohigh.com/settings" target="_blank">MSH Dashboard → Settings</a></p>
                </td>
            </tr>
            <tr>
                <th>Site ID</th>
                <td>
                    <code><?php echo esc_html( $site_id ?: 'Will be assigned after first sync' ); ?></code>
                </td>
            </tr>
            <tr>
                <th>Connection Status</th>
                <td>
                    <?php if ( $api_key ) : ?>
                        <span style="color:#00b894;font-weight:600;">✓ Connected</span>
                        <?php if ( $last_sync ) : ?>
                            <br><small>Last sync: <?php echo esc_html( $last_sync ); ?></small>
                        <?php endif; ?>
                    <?php else : ?>
                        <span style="color:#d63031;">✗ Not Connected</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <h2>REST API Endpoints</h2>
        <p>When connected, your MSH dashboard uses these endpoints:</p>
        <table class="widefat fixed striped">
            <thead><tr><th>Endpoint</th><th>Method</th><th>Description</th></tr></thead>
            <tbody>
                <tr><td><code>/wp-json/msh/v1/publish</code></td><td>POST</td><td>Publish content from MSH dashboard</td></tr>
                <tr><td><code>/wp-json/msh/v1/meta</code></td><td>POST</td><td>Update SEO meta for any post</td></tr>
                <tr><td><code>/wp-json/msh/v1/redirect</code></td><td>POST</td><td>Create redirect rules remotely</td></tr>
                <tr><td><code>/wp-json/msh/v1/status</code></td><td>GET</td><td>Check plugin status and site info</td></tr>
                <tr><td><code>/wp-json/msh/v1/content-inventory</code></td><td>GET</td><td>Get all published content</td></tr>
                <tr><td><code>/wp-json/msh/v1/keywords</code></td><td>POST</td><td>Sync focus keywords from MSH</td></tr>
            </tbody>
        </table>

        <?php submit_button( 'Save Connection' ); ?>
    </form>
</div>
