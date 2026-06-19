<?php

namespace Pie\CustomFunctionsMUPlugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the PIE admin override checkbox on a user's profile page.
 *
 * Only visible to @pie.co.de users.
 *
 * @since 1.5.2
 * @param \WP_User $user The user whose profile is being edited.
 */
function render_pie_admin_override_field( \WP_User $user ): void {
	if ( ! is_pie_admin_email( wp_get_current_user()->user_email ) ) {
		return;
	}

	if ( is_pie_admin_email( $user->user_email ) ) {
		return;
	}

	$checked = (bool) get_user_meta( $user->ID, 'pie_admin_override', true );

	include plugin_dir_path( __FILE__ ) . 'templates/pie-admin-override-field.php';
}
add_action( 'edit_user_profile', __NAMESPACE__ . '\render_pie_admin_override_field', 999 );
add_action( 'show_user_profile', __NAMESPACE__ . '\render_pie_admin_override_field', 999 );

/**
 * Save the PIE admin override meta when a user profile is updated.
 *
 * Only @pie.co.de users can set this value.
 *
 * @since 1.5.2
 * @param int $user_id The ID of the user being saved.
 */
function save_pie_admin_override_field( int $user_id ): void {
	if ( ! isset( $_POST['pie_admin_override_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pie_admin_override_nonce'] ) ), 'pie_admin_override_' . $user_id ) ) {
		return;
	}

	if ( ! is_pie_admin_email( wp_get_current_user()->user_email ) ) {
		return;
	}

	// Extra safety check in case grant_pie_admin_caps() is ever broken or removed.
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above
	$value = isset( $_POST['pie_admin_override'] ) ? sanitize_text_field( wp_unslash( $_POST['pie_admin_override'] ) ) : '';

	if ( '1' === $value ) {
		update_user_meta( $user_id, 'pie_admin_override', true );
	} else {
		delete_user_meta( $user_id, 'pie_admin_override' );
	}
}
add_action( 'edit_user_profile_update', __NAMESPACE__ . '\save_pie_admin_override_field' );
add_action( 'personal_options_update', __NAMESPACE__ . '\save_pie_admin_override_field' );
