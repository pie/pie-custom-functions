<?php
/**
 * Update Watchdog - Tracks in-progress plugin and theme updates.
 *
 * Schedules a one-off WP-Cron event when each update begins. If the update
 * completes normally the event is cancelled. If the server crashes and the
 * completion hook never fires, the event runs and sends an alert email.
 *
 * @package pie-custom-functions
 */

namespace PIE\UpdateWatchdog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OPTION_KEY    = 'pie_update_watchdog';
const STUCK_MINUTES = 15;
const CRON_HOOK     = 'pie_update_watchdog_check';

add_filter( 'upgrader_pre_install', __NAMESPACE__ . '\record_update_start', 10, 2 );
add_action( 'upgrader_process_complete', __NAMESPACE__ . '\record_update_complete', 10, 2 );
add_action( CRON_HOOK, __NAMESPACE__ . '\check_stuck_update' );

/**
 * Record the start of a plugin or theme update and schedule a watchdog check.
 *
 * Hooked to upgrader_pre_install — must return $response unchanged to avoid
 * aborting the update.
 *
 * @param bool|\WP_Error $response   Installation response.
 * @param array          $upgrade_context Upgrader context containing the extension identifier (e.g., 'plugin' or 'theme').
 * @return bool|\WP_Error $response  Unchanged installation response.
 */
function record_update_start( bool|\WP_Error $response, array $upgrade_context ): bool|\WP_Error {
	$type = '';
	$key  = '';
	$name = '';

	// upgrader_pre_install context never contains 'action' or 'type' — infer from present keys.
	if ( isset( $upgrade_context['plugin'] ) ) {
		$type = 'plugin';
		$key  = $upgrade_context['plugin'];
		$name = resolve_plugin_name( $key );
	} elseif ( isset( $upgrade_context['theme'] ) ) {
		$type = 'theme';
		$key  = $upgrade_context['theme'];
		$name = resolve_theme_name( $key );
	}

	if ( '' === $key ) {
		return $response;
	}

	$watchlist = get_option( OPTION_KEY, array() );
	if ( ! is_array( $watchlist ) ) {
		$watchlist = array();
	}
	$watchlist[ $key ] = array(
		'type'       => $type,
		'name'       => $name,
		'started_at' => time(),
	);
	update_option( OPTION_KEY, $watchlist, false );

	// Clear any previously scheduled check for this key, then schedule a fresh one.
	wp_clear_scheduled_hook( CRON_HOOK, array( $key ) );
	$fire_at = time() + STUCK_MINUTES * MINUTE_IN_SECONDS;
	wp_schedule_single_event( $fire_at, CRON_HOOK, array( $key ) );

	return $response;
}

/**
 * Remove completed update(s) from the watchlist and cancel their watchdog events.
 *
 * Handles both single-item and bulk upgrades by checking for both the
 * singular key (plugin/theme) and the plural key (plugins/themes).
 *
 * @param \WP_Upgrader $_upgrader       The upgrader instance (unused).
 * @param array        $upgrade_context Upgrader context containing type and extension key(s).
 */
function record_update_complete( \WP_Upgrader $_upgrader, array $upgrade_context ): void {
	$watchlist = get_option( OPTION_KEY, array() );
	if ( ! is_array( $watchlist ) ) {
		$watchlist = array();
	}

	if ( array() === $watchlist ) {
		return;
	}

	$type = $upgrade_context['type'] ?? '';
	$keys = array();

	if ( 'plugin' === $type ) {
		if ( isset( $upgrade_context['plugins'] ) ) {
			$keys = $upgrade_context['plugins'];
		} elseif ( isset( $upgrade_context['plugin'] ) ) {
			$keys = array( $upgrade_context['plugin'] );
		}
	} elseif ( 'theme' === $type ) {
		if ( isset( $upgrade_context['themes'] ) ) {
			$keys = $upgrade_context['themes'];
		} elseif ( isset( $upgrade_context['theme'] ) ) {
			$keys = array( $upgrade_context['theme'] );
		}
	}

	foreach ( $keys as $key ) {
		unset( $watchlist[ $key ] );
		wp_clear_scheduled_hook( CRON_HOOK, array( $key ) );
	}

	if ( array() === $watchlist ) {
		delete_option( OPTION_KEY );
	} else {
		update_option( OPTION_KEY, $watchlist, false );
	}
}

/**
 * Check whether a specific extension's update is still in-progress.
 *
 * Fires via a one-off WP-Cron event scheduled at update start. If the entry
 * still exists in the watchlist the update never completed — send an alert
 * and clear the entry. If it's gone the update finished normally.
 *
 * @param string $key Extension key (plugin relative path or theme slug).
 */
function check_stuck_update( string $key ): void {
	$watchlist = get_option( OPTION_KEY, array() );
	if ( ! is_array( $watchlist ) ) {
		$watchlist = array();
	}

	if ( ! isset( $watchlist[ $key ] ) ) {
		return;
	}

	$entry = $watchlist[ $key ];
	unset( $watchlist[ $key ] );

	if ( array() === $watchlist ) {
		delete_option( OPTION_KEY );
	} else {
		update_option( OPTION_KEY, $watchlist, false );
	}

	send_stuck_alert( array( $key => $entry ) );
}

/**
 * Send an email alert for one or more stuck updates.
 *
 * The recipient address is filterable via pie_update_watchdog_alert_email.
 * Defaults to the site's admin email.
 *
 * @param array $stuck Map of extension key => watchlist entry data.
 */
function send_stuck_alert( array $stuck ): void {
	$site_name   = get_bloginfo( 'name' );
	$site_url    = get_site_url();
	$to          = apply_filters( 'pie_update_watchdog_alert_email', get_option( 'admin_email' ) );
	$count       = count( $stuck );
	$upgrade_dir = trailingslashit( WP_CONTENT_DIR ) . 'upgrade/';

	$subject = sprintf(
		'[%s] %s',
		$site_name,
		sprintf(
			_n( '%d stuck update detected', '%d stuck updates detected', $count, 'pie-custom-functions' ),
			$count
		)
	);

	ob_start();
	include __DIR__ . '/templates/emails/stuck-update-alert.php';
	$body = ob_get_clean();

	wp_mail( $to, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
}

/**
 * Resolve a human-readable plugin name from its relative file path.
 *
 * @param string $plugin_file Relative path, e.g. 'akismet/akismet.php'.
 * @return string Plugin name, or the file path as fallback.
 */
function resolve_plugin_name( string $plugin_file ): string {
	if ( ! function_exists( 'get_plugin_data' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$path = WP_PLUGIN_DIR . '/' . $plugin_file;

	if ( ! is_readable( $path ) ) {
		return $plugin_file;
	}

	$data = get_plugin_data( $path, false, false );
	return '' !== $data['Name'] ? $data['Name'] : $plugin_file;
}

/**
 * Resolve a human-readable theme name from its slug.
 *
 * @param string $theme_slug Theme directory name.
 * @return string Theme name, or the slug as fallback.
 */
function resolve_theme_name( string $theme_slug ): string {
	$theme = wp_get_theme( $theme_slug );
	$name  = $theme->get( 'Name' );
	return '' !== $name ? $name : $theme_slug;
}
