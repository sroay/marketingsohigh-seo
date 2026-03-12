<?php if ( ! defined( 'ABSPATH' ) ) exit;
// Handle add/delete
if ( isset( $_POST['msh_add_redirect'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'msh_redirect_action' ) ) {
    MSH_Module_Redirects::create( [
        'source'      => sanitize_text_field( $_POST['source'] ?? '' ),
        'destination' => sanitize_text_field( $_POST['destination'] ?? '' ),
        'type'        => (int) ( $_POST['type'] ?? 301 ),
        'match_mode'  => sanitize_text_field( $_POST['match_mode'] ?? 'exact' ),
    ] );
    echo '<div class="notice notice-success"><p>Redirect created.</p></div>';
}
if ( isset( $_GET['delete_redirect'] ) && wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'msh_delete_redirect' ) ) {
    MSH_Module_Redirects::delete( (int) $_GET['delete_redirect'] );
    echo '<div class="notice notice-success"><p>Redirect deleted.</p></div>';
}

global $wpdb;
$redirects = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}msh_redirects ORDER BY id DESC LIMIT 100", ARRAY_A );
?>
<div class="wrap msh-wrap">
    <h1>Redirections</h1>

    <h2>Add New Redirect</h2>
    <form method="post">
        <?php wp_nonce_field( 'msh_redirect_action' ); ?>
        <table class="form-table">
            <tr><th>Source URL</th><td><input type="text" name="source" class="regular-text" placeholder="/old-page" required /></td></tr>
            <tr><th>Destination URL</th><td><input type="text" name="destination" class="regular-text" placeholder="/new-page" /></td></tr>
            <tr><th>Type</th><td>
                <select name="type">
                    <option value="301">301 Permanent</option>
                    <option value="302">302 Temporary</option>
                    <option value="307">307 Temporary (preserve method)</option>
                    <option value="410">410 Gone</option>
                    <option value="451">451 Legal</option>
                </select>
            </td></tr>
            <tr><th>Match Mode</th><td>
                <select name="match_mode">
                    <option value="exact">Exact</option>
                    <option value="contains">Contains</option>
                    <option value="starts_with">Starts With</option>
                    <option value="ends_with">Ends With</option>
                    <option value="regex">Regex</option>
                </select>
            </td></tr>
        </table>
        <button type="submit" name="msh_add_redirect" class="button button-primary">Add Redirect</button>
    </form>

    <h2>Active Redirects (<?php echo count( $redirects ); ?>)</h2>
    <table class="widefat fixed striped">
        <thead><tr><th>Source</th><th>Destination</th><th>Type</th><th>Mode</th><th>Hits</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ( $redirects as $r ) : ?>
            <tr>
                <td><code><?php echo esc_html( $r['source'] ); ?></code></td>
                <td><?php echo esc_html( $r['destination'] ); ?></td>
                <td><?php echo (int) $r['type']; ?></td>
                <td><?php echo esc_html( $r['match_mode'] ); ?></td>
                <td><?php echo (int) $r['hits']; ?></td>
                <td><a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=msh-redirects&delete_redirect=' . $r['id'] ), 'msh_delete_redirect' ); ?>" onclick="return confirm('Delete this redirect?')" class="button button-small">Delete</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if ( empty( $redirects ) ) : ?>
            <tr><td colspan="6">No redirects yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
