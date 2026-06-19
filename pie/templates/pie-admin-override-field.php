<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<table class="form-table">
	<tr>
		<th><label for="pie_admin_override"><?php esc_html_e( 'Grant PIE admin privileges', 'pie-custom-functions' ); ?></label></th>
		<td>
			<?php wp_nonce_field( 'pie_admin_override_' . $user->ID, 'pie_admin_override_nonce' ); ?>
			<input type="checkbox" id="pie_admin_override" name="pie_admin_override" value="1" <?php checked( $checked ); ?> />
			<span class="description"><?php esc_html_e( 'Grants the same administrator capabilities as a @pie.co.de email address.', 'pie-custom-functions' ); ?></span>
		</td>
	</tr>
</table>
