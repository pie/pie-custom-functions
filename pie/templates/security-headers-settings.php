<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="wrap">
	<h1><?php esc_html_e( 'Pie Security Headers', 'pie-custom-functions' ); ?></h1>

	<?php if ( $override_active ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: PHP constant name */
						__( 'The <code>%s</code> constant is defined in wp-config.php and overrides the saved value below.', 'pie-custom-functions' ),
						'PIE_CSP_HEADER'
					),
					array( 'code' => array() )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'pie_security_headers' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="pie_csp_header">
						<?php esc_html_e( 'Content-Security-Policy', 'pie-custom-functions' ); ?>
					</label>
				</th>
				<td>
					<textarea
						name="pie_csp_header"
						id="pie_csp_header"
						rows="6"
						class="large-text code"
					><?php echo esc_textarea( (string) get_option( 'pie_csp_header', '' ) ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Enter a valid Content-Security-Policy directive string. Sent as a response header on all page loads including wp-admin and the login page.', 'pie-custom-functions' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
</div>
