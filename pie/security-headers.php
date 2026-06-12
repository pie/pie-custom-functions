<?php

namespace Pie\SecurityHeaders;

use function Pie\CustomFunctionsMUPlugin\is_pie_admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the active Content-Security-Policy header value.
 *
 * Resolution order (highest to lowest priority):
 * 1. PIE_CSP_HEADER constant — emergency failsafe defined in wp-config.php.
 *    Use only when a bad CSP has broken admin access and the settings page is unreachable.
 *    When defined and non-empty, this value is returned immediately.
 * 		(usage: define( 'PIE_CSP_HEADER', "add CSP rules here" );)
 *
 * 2. pie_csp_header option — the normal CSP value saved via the settings page.
 *
 * @since 1.4.0
 * @return string Sanitized CSP value, or empty string if not configured.
 */
function get_csp_header(): string {
	if ( defined( 'PIE_CSP_HEADER' ) && '' !== trim( (string) \PIE_CSP_HEADER ) ) {
		return trim( str_replace( array( "\r", "\n" ), '', (string) \PIE_CSP_HEADER ) );
	}

	$csp = (string) get_option( 'pie_csp_header', '' );

	return trim( str_replace( array( "\r", "\n" ), '', $csp ) );
}

/**
 * Add the Content-Security-Policy header to frontend responses.
 *
 * Filters the headers array that WordPress sends for all standard frontend
 * page loads. Does nothing when no CSP is configured.
 *
 * @since 1.4.0
 * @param string[] $headers Associative array of response headers.
 * @return string[] Modified headers array.
 */
function add_csp_to_wp_headers( array $headers ): array {
	$csp = get_csp_header();

	if ( '' !== $csp ) {
		$headers['Content-Security-Policy'] = $csp;
	}

	return $headers;
}
add_filter( 'wp_headers', __NAMESPACE__ . '\\add_csp_to_wp_headers' );

/**
 * Send the Content-Security-Policy header for admin and login page contexts.
 *
 * wp_headers does not fire for wp-admin or wp-login.php, so the header
 * is sent directly via header() in those contexts. AJAX requests are
 * skipped because browsers do not enforce CSP on XHR/fetch responses.
 *
 * @since 1.4.0
 * @return void
 */
function send_csp_header(): void {
	if ( wp_doing_ajax() ) {
		return;
	}

	$csp = get_csp_header();

	if ( '' !== $csp && ! headers_sent() ) {
		header( 'Content-Security-Policy: ' . $csp );
	}
}
add_action( 'admin_init', __NAMESPACE__ . '\\send_csp_header' );
add_action( 'login_init', __NAMESPACE__ . '\\send_csp_header' );

/**
 * Sanitize the pie_csp_header option before saving.
 *
 * Unslashes, strips newlines/carriage returns to prevent header injection,
 * and trims whitespace so a blank submission saves as an empty string.
 * All other valid CSP characters are preserved. Returns the existing option
 * unchanged if the current user is not a Pie admin.
 *
 * @since 1.4.0
 * @param mixed $value Raw submitted value.
 * @return string Sanitized CSP string.
 */
function sanitize_csp_option( mixed $value ): string {
	if ( ! is_pie_admin() ) {
		return (string) get_option( 'pie_csp_header', '' );
	}

	if ( is_array( $value ) ) {
		return '';
	}

	$value = wp_unslash( $value );

	return trim( str_replace( array( "\r", "\n" ), '', (string) $value ) );
}

/**
 * Register the pie_csp_header setting.
 *
 * @since 1.4.0
 * @return void
 */
function register_csp_settings(): void {
	register_setting(
		'pie_security_headers',
		'pie_csp_header',
		array(
			'type'              => 'string',
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_csp_option',
			'default'           => '',
		)
	);
}
add_action( 'admin_init', __NAMESPACE__ . '\\register_csp_settings' );

/**
 * Add the Pie Security Headers settings page under Settings.
 *
 * Access is controlled by is_pie_admin(), not by manage_options. The page
 * is only registered for Pie admin users, so WordPress never exposes it to
 * regular administrators — direct URL access returns a "page not found"
 * error from WordPress before our render callback is ever reached.
 * The render callback repeats the is_pie_admin() check as defence in depth.
 *
 * @since 1.4.0
 * @return void
 */
function register_settings_page(): void {
	if ( ! is_pie_admin() ) {
		return;
	}

	add_options_page(
		__( 'Pie Security Headers', 'pie-custom-functions' ),
		__( 'Pie Security Headers', 'pie-custom-functions' ),
		'manage_options',
		'pie-security-headers',
		__NAMESPACE__ . '\\render_settings_page'
	);
}
add_action( 'admin_menu', __NAMESPACE__ . '\\register_settings_page' );

/**
 * Render the Pie Security Headers settings page.
 *
 * @since 1.4.0
 * @return void
 */
function render_settings_page(): void {
	if ( ! is_pie_admin() ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'pie-custom-functions' ) );
	}

	$override_active = defined( 'PIE_CSP_HEADER' ) && '' !== trim( (string) \PIE_CSP_HEADER );

	require __DIR__ . '/templates/security-headers-settings.php';
}
