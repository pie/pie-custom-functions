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
 * @param mixed $response        Pass-through value; return as-is.
 * @param array $upgrade_context Upgrader context containing type, action, and the extension key.
 * @return mixed Unchanged $response.
 */
function record_update_start( mixed $response, array $upgrade_context ): mixed {
	if ( ! isset( $upgrade_context['action'] ) || 'update' !== $upgrade_context['action'] ) {
		return $response;
	}

	$type = $upgrade_context['type'] ?? '';
	$key  = '';
	$name = '';

	if ( 'plugin' === $type && isset( $upgrade_context['plugin'] ) ) {
		$key  = $upgrade_context['plugin'];
		$name = resolve_plugin_name( $key );
	} elseif ( 'theme' === $type && isset( $upgrade_context['theme'] ) ) {
		$key  = $upgrade_context['theme'];
		$name = resolve_theme_name( $key );
	}

	if ( '' === $key ) {
		return $response;
	}

	$watchlist         = get_option( OPTION_KEY, array() );
	$watchlist[ $key ] = array(
		'type'       => $type,
		'name'       => $name,
		'started_at' => time(),
	);
	update_option( OPTION_KEY, $watchlist, false );

	// Clear any previously scheduled check for this key, then schedule a fresh one.
	wp_clear_scheduled_hook( CRON_HOOK, array( $key ) );
	wp_schedule_single_event( time() + STUCK_MINUTES * MINUTE_IN_SECONDS, CRON_HOOK, array( $key ) );

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
	$site_name = get_bloginfo( 'name' );
	$site_url  = get_site_url();
	$to        = apply_filters( 'pie_update_watchdog_alert_email', get_option( 'admin_email' ) );
	$count     = count( $stuck );

	$subject = sprintf(
		'[%s] %s',
		$site_name,
		1 === $count
			? __( 'Stuck update detected', 'pie-custom-functions' )
			/* translators: %d: number of stuck updates */
			: sprintf( __( '%d stuck updates detected', 'pie-custom-functions' ), $count )
	);

	$lines = array(
		sprintf(
			/* translators: 1: plural suffix, 2: site name, 3: site URL */
			__( 'The following update%1$s on %2$s (%3$s) appear to have stalled:', 'pie-custom-functions' ),
			$count > 1 ? 's' : '',
			$site_name,
			$site_url
		),
		'',
	);

	foreach ( $stuck as $key => $entry ) {
		$started  = wp_date( 'Y-m-d H:i:s T', $entry['started_at'] );
		$duration = human_time_diff( $entry['started_at'] );

		$lines[] = '  ' . sprintf( '%s (%s)', $entry['name'], ucfirst( $entry['type'] ) );
		// translators: %s: plugin or theme slug.
		$lines[] = '  ' . sprintf( __( 'Slug:     %s', 'pie-custom-functions' ), $key );
		// translators: %s: timestamp of when the update started.
		$lines[] = '  ' . sprintf( __( 'Started:  %s', 'pie-custom-functions' ), $started );
		// translators: %s: human-readable duration since the update began.
		$lines[] = '  ' . sprintf( __( 'Duration: %s', 'pie-custom-functions' ), $duration );
		$lines[] = '';
	}

	$upgrade_dir = trailingslashit( WP_CONTENT_DIR ) . 'upgrade/';

	$lines[] = __( '--- What likely happened ---', 'pie-custom-functions' );
	$lines[] = __( 'The server may have crashed or timed out mid-update, leaving the extension in a partial or broken state.', 'pie-custom-functions' );
	$lines[] = '';
	$lines[] = __( '--- How to resolve ---', 'pie-custom-functions' );
	$lines[] = '';
	$lines[] = __( '1. Clear partial update files', 'pie-custom-functions' );
	// translators: %s: path to the WordPress upgrade directory.
	$lines[] = sprintf( __( '   Check %s for any leftover temp directories and delete them.', 'pie-custom-functions' ), $upgrade_dir );
	$lines[] = __( '   These are safe to remove — WordPress recreates them as needed.', 'pie-custom-functions' );
	$lines[] = '';
	$lines[] = __( '2. Verify the extension directory', 'pie-custom-functions' );
	$lines[] = __( '   If the plugin or theme directory is corrupted or missing files, reinstall it:', 'pie-custom-functions' );
	$lines[] = __( '   - Via wp-admin: Plugins > Add New > upload a fresh copy', 'pie-custom-functions' );
	$lines[] = __( '   - Via WP-CLI:   wp plugin install <slug> --force', 'pie-custom-functions' );
	$lines[] = '';
	$lines[] = __( '3. Clear the WordPress update lock', 'pie-custom-functions' );
	$lines[] = __( '   If the update still shows as pending or unavailable, clear the update transients:', 'pie-custom-functions' );
	$lines[] = __( '   - Via WP-CLI:   wp transient delete --network update_plugins && wp transient delete --network auto_updater.lock', 'pie-custom-functions' );
	$lines[] = __( '   - Via wp-admin: Dashboard > Updates > Check Again', 'pie-custom-functions' );
	$lines[] = '';
	$lines[] = __( '4. Dismiss this alert', 'pie-custom-functions' );
	$lines[] = __( '   Once resolved, remove the stale watchdog entry so future alerts are not suppressed:', 'pie-custom-functions' );
	$lines[] = __( '   - Via WP-CLI:   wp option delete pie_update_watchdog', 'pie-custom-functions' );
	$lines[] = __( "   - Via database: delete the row with option_name = 'pie_update_watchdog' from wp_options", 'pie-custom-functions' );
	$lines[] = '';
	// translators: %s: URL to the site's admin area.
	$lines[] = sprintf( __( 'Admin area: %s', 'pie-custom-functions' ), admin_url() );

	wp_mail( $to, $subject, implode( "\n", $lines ) );
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
