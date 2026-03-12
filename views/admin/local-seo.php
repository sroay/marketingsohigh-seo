<?php if ( ! defined( 'ABSPATH' ) ) exit;
$settings = get_option( 'msh_local_seo_settings', [] );
?>
<div class="wrap msh-wrap">
    <h1>Local SEO</h1>
    <form method="post" action="options.php">
        <?php settings_fields( 'msh_local' ); ?>
        <table class="form-table">
            <tr><th>Business Name</th><td><input type="text" name="msh_local_seo_settings[business_name]" value="<?php echo esc_attr( $settings['business_name'] ?? '' ); ?>" class="regular-text" /></td></tr>
            <tr><th>Business Type</th><td>
                <select name="msh_local_seo_settings[business_type]">
                    <option value="Organization" <?php selected( $settings['business_type'] ?? '', 'Organization' ); ?>>Organization</option>
                    <option value="LocalBusiness" <?php selected( $settings['business_type'] ?? '', 'LocalBusiness' ); ?>>Local Business</option>
                    <option value="Restaurant" <?php selected( $settings['business_type'] ?? '', 'Restaurant' ); ?>>Restaurant</option>
                    <option value="Store" <?php selected( $settings['business_type'] ?? '', 'Store' ); ?>>Store</option>
                    <option value="ProfessionalService" <?php selected( $settings['business_type'] ?? '', 'ProfessionalService' ); ?>>Professional Service</option>
                    <option value="Person" <?php selected( $settings['business_type'] ?? '', 'Person' ); ?>>Person</option>
                </select>
            </td></tr>
            <tr><th>Phone</th><td><input type="tel" name="msh_local_seo_settings[phone]" value="<?php echo esc_attr( $settings['phone'] ?? '' ); ?>" /></td></tr>
            <tr><th>Email</th><td><input type="email" name="msh_local_seo_settings[email]" value="<?php echo esc_attr( $settings['email'] ?? '' ); ?>" /></td></tr>
            <tr><th>Logo URL</th><td><input type="url" name="msh_local_seo_settings[logo]" value="<?php echo esc_url( $settings['logo'] ?? '' ); ?>" class="regular-text" /></td></tr>
            <tr><th>Street Address</th><td><input type="text" name="msh_local_seo_settings[address][street]" value="<?php echo esc_attr( $settings['address']['street'] ?? '' ); ?>" class="regular-text" /></td></tr>
            <tr><th>City</th><td><input type="text" name="msh_local_seo_settings[address][city]" value="<?php echo esc_attr( $settings['address']['city'] ?? '' ); ?>" /></td></tr>
            <tr><th>State/Region</th><td><input type="text" name="msh_local_seo_settings[address][state]" value="<?php echo esc_attr( $settings['address']['state'] ?? '' ); ?>" /></td></tr>
            <tr><th>ZIP/Postal Code</th><td><input type="text" name="msh_local_seo_settings[address][zip]" value="<?php echo esc_attr( $settings['address']['zip'] ?? '' ); ?>" /></td></tr>
            <tr><th>Country</th><td><input type="text" name="msh_local_seo_settings[address][country]" value="<?php echo esc_attr( $settings['address']['country'] ?? '' ); ?>" /></td></tr>
            <tr><th>Latitude</th><td><input type="text" name="msh_local_seo_settings[latitude]" value="<?php echo esc_attr( $settings['latitude'] ?? '' ); ?>" /></td></tr>
            <tr><th>Longitude</th><td><input type="text" name="msh_local_seo_settings[longitude]" value="<?php echo esc_attr( $settings['longitude'] ?? '' ); ?>" /></td></tr>
        </table>

        <h2>Social Profiles</h2>
        <table class="form-table">
            <tr><th>Facebook</th><td><input type="url" name="msh_local_seo_settings[facebook]" value="<?php echo esc_url( $settings['facebook'] ?? '' ); ?>" class="regular-text" /></td></tr>
            <tr><th>Twitter/X</th><td><input type="url" name="msh_local_seo_settings[twitter]" value="<?php echo esc_url( $settings['twitter'] ?? '' ); ?>" class="regular-text" /></td></tr>
            <tr><th>LinkedIn</th><td><input type="url" name="msh_local_seo_settings[linkedin]" value="<?php echo esc_url( $settings['linkedin'] ?? '' ); ?>" class="regular-text" /></td></tr>
            <tr><th>Instagram</th><td><input type="url" name="msh_local_seo_settings[instagram]" value="<?php echo esc_url( $settings['instagram'] ?? '' ); ?>" class="regular-text" /></td></tr>
            <tr><th>YouTube</th><td><input type="url" name="msh_local_seo_settings[youtube]" value="<?php echo esc_url( $settings['youtube'] ?? '' ); ?>" class="regular-text" /></td></tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>
