<?php if ( ! defined( 'ABSPATH' ) ) exit;
$page = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
$logs = MSH_Module_404_Monitor::get_logs( 50, $page );
$total = MSH_Module_404_Monitor::get_total();
?>
<div class="wrap msh-wrap">
    <h1>404 Monitor (<?php echo (int) $total; ?> entries)</h1>
    <table class="widefat fixed striped">
        <thead><tr><th>URL</th><th>Hits</th><th>Referrer</th><th>Last Seen</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ( $logs as $log ) : ?>
            <tr>
                <td><code><?php echo esc_html( $log['url'] ); ?></code></td>
                <td><?php echo (int) $log['hits']; ?></td>
                <td><?php echo esc_html( $log['referrer'] ? wp_parse_url( $log['referrer'], PHP_URL_HOST ) : '-' ); ?></td>
                <td><?php echo esc_html( $log['updated_at'] ); ?></td>
                <td>
                    <a href="<?php echo admin_url( 'admin.php?page=msh-redirects&source=' . urlencode( $log['url'] ) ); ?>" class="button button-small">Create Redirect</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ( empty( $logs ) ) : ?>
            <tr><td colspan="5">No 404 errors logged. Great!</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
