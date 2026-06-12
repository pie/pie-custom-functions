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
 * 1. PIE_CSP_HEADER constant — emergency override via wp-config.php.
 *    When defined and non-empty, it is returned immediately. The
 *    pie_security_csp_header filter does NOT run in this case, making
 *    the constant a true hard override.
 * 2. pie_csp_header option — saved via the settings page.
 * 3. pie_security_csp_header filter — developer override of the saved
 *    option only; never applied when the constant is active.
 *
 * Newlines and carriage returns are stripped before returning to prevent
 * HTTP header injection. All other valid CSP characters are preserved.
 * Whitespace-only values are treated as empty and not sent.
 *
 * @since 1.5.0
 * @return string Sanitized CSP value, or empty string if not configured.
 */
function get_csp_header(): string {
	// Constant is a hard override: skip the filter entirely.
	if ( defined( 'PIE_CSP_HEADER' ) && '' !== trim( (string) \PIE_CSP_HEADER ) ) {
		return trim( str_replace( array( "\r", "\n" ), '', (string) \PIE_CSP_HEADER ) );
	}

	$csp = (string) get_option( 'pie_csp_header', '' );

	/** This filter does not run when PIE_CSP_HEADER is defined. */
	$csp = (string) apply_filters( 'pie_security_csp_header', $csp );

	return trim( str_replace( array( "\r", "\n" ), '', $csp ) );
}

/**
 * Add the Content-Security-Policy header to frontend responses.
 *
 * Filters the headers array that WordPress sends for all standard frontend
 * page loads. Does nothing when no CSP is configured.
 *
 * @since 1.5.0
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
 * @since 1.5.0
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
 * Strips newlines and carriage returns to prevent header injection while
 * preserving all valid CSP syntax. Returns the existing option unchanged
 * if the current user is not a Pie admin.
 *
 * @since 1.5.0
 * @param mixed $value Raw submitted value.
 * @return string Sanitized CSP string.
 */
function sanitize_csp_option( mixed $value ): string {
	if ( ! is_pie_admin() ) {
		return (string) get_option( 'pie_csp_header', '' );
	}

	return str_replace( array( "\r", "\n" ), '', (string) $value );
}

/**
 * Register the pie_csp_header setting.
 *
 * @since 1.5.0
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
 * @since 1.5.0
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
 * @since 1.5.0
 * @return void
 */
function render_settings_page(): void {
	if ( ! is_pie_admin() ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'pie-custom-functions' ) );
	}

	$override_active = defined( 'PIE_CSP_HEADER' ) && '' !== trim( (string) \PIE_CSP_HEADER );
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
	<?php
}
