<?php

/**
 * Update Watchdog - Tracks in-progress plugin and theme updates.
 *
 * Records a timestamp when each update begins and clears it on completion.
 * A scheduled check runs every 15 minutes to alert via email when an update
 * appears stuck (i.e. has been in-progress longer than the threshold).
 */

namespace PIE\UpdateWatchdog;

const OPTION_KEY    = 'pie_update_watchdog';
const STUCK_MINUTES = 15;
const CRON_HOOK     = 'pie_update_watchdog_check';
const CRON_INTERVAL = 'pie_every_15_minutes';

add_filter( 'cron_schedules',            __NAMESPACE__ . '\register_cron_interval' );
add_action( 'init',                      __NAMESPACE__ . '\schedule_cron' );
add_filter( 'upgrader_pre_install',      __NAMESPACE__ . '\record_update_start', 10, 2 );
add_action( 'upgrader_process_complete', __NAMESPACE__ . '\record_update_complete', 10, 2 );
add_action( CRON_HOOK,                   __NAMESPACE__ . '\check_for_stuck_updates' );

/**
 * Register the 15-minute cron interval.
 *
 * @param array $schedules Existing cron schedules.
 * @return array Modified schedules.
 */
function register_cron_interval( array $schedules ): array {
    $schedules[ CRON_INTERVAL ] = [
        'interval' => STUCK_MINUTES * MINUTE_IN_SECONDS,
        'display'  => __( 'Every 15 Minutes', 'pie-custom-functions' ),
    ];
    return $schedules;
}

/**
 * Ensure the watchdog cron event is scheduled.
 */
function schedule_cron(): void {
    if ( false === wp_next_scheduled( CRON_HOOK ) ) {
        wp_schedule_event( time(), CRON_INTERVAL, CRON_HOOK );
    }
}

/**
 * Record the start of a plugin or theme update before installation begins.
 *
 * Hooked to upgrader_pre_install — must return $response unchanged to avoid
 * aborting the update.
 *
 * @param mixed $response   Pass-through value; return as-is.
 * @param array $hook_extra Upgrader context containing type, action, and the extension key.
 * @return mixed Unchanged $response.
 */
function record_update_start( mixed $response, array $hook_extra ): mixed {
    if ( ! isset( $hook_extra['action'] ) || 'update' !== $hook_extra['action'] ) {
        return $response;
    }

    $type = $hook_extra['type'] ?? '';
    $key  = '';
    $name = '';

    if ( 'plugin' === $type && isset( $hook_extra['plugin'] ) ) {
        $key  = $hook_extra['plugin'];
        $name = resolve_plugin_name( $key );
    } elseif ( 'theme' === $type && isset( $hook_extra['theme'] ) ) {
        $key  = $hook_extra['theme'];
        $name = resolve_theme_name( $key );
    }

    if ( '' === $key ) {
        return $response;
    }

    $watchlist         = get_option( OPTION_KEY, [] );
    $watchlist[ $key ] = [
        'type'       => $type,
        'name'       => $name,
        'started_at' => time(),
    ];
    update_option( OPTION_KEY, $watchlist, false );

    return $response;
}

/**
 * Remove completed update(s) from the watchlist.
 *
 * Handles both single-item and bulk upgrades by checking for both the
 * singular key (plugin/theme) and the plural key (plugins/themes).
 *
 * @param \WP_Upgrader $_upgrader  The upgrader instance (unused).
 * @param array        $hook_extra Upgrader context containing type and extension key(s).
 */
function record_update_complete( \WP_Upgrader $_upgrader, array $hook_extra ): void {
    $watchlist = get_option( OPTION_KEY, [] );

    if ( [] === $watchlist ) {
        return;
    }

    $type = $hook_extra['type'] ?? '';
    $keys = [];

    if ( 'plugin' === $type ) {
        if ( isset( $hook_extra['plugins'] ) ) {
            $keys = $hook_extra['plugins'];
        } elseif ( isset( $hook_extra['plugin'] ) ) {
            $keys = [ $hook_extra['plugin'] ];
        }
    } elseif ( 'theme' === $type ) {
        if ( isset( $hook_extra['themes'] ) ) {
            $keys = $hook_extra['themes'];
        } elseif ( isset( $hook_extra['theme'] ) ) {
            $keys = [ $hook_extra['theme'] ];
        }
    }

    foreach ( $keys as $key ) {
        unset( $watchlist[ $key ] );
    }

    if ( [] === $watchlist ) {
        delete_option( OPTION_KEY );
    } else {
        update_option( OPTION_KEY, $watchlist, false );
    }
}

/**
 * Detect updates stuck beyond the threshold and send a single alert email.
 *
 * Each entry is marked with reported_at once alerted so it is not re-reported
 * on subsequent cron runs.
 */
function check_for_stuck_updates(): void {
    $watchlist = get_option( OPTION_KEY, [] );

    if ( [] === $watchlist ) {
        return;
    }

    $threshold = STUCK_MINUTES * MINUTE_IN_SECONDS;
    $now       = time();
    $stuck     = [];

    foreach ( $watchlist as $key => $entry ) {
        $age = $now - $entry['started_at'];
        if ( $age >= $threshold && ! isset( $entry['reported_at'] ) ) {
            $stuck[ $key ]                    = array_merge( $entry, [ 'duration' => $age ] );
            $watchlist[ $key ]['reported_at'] = $now;
        }
    }

    if ( [] === $stuck ) {
        return;
    }

    update_option( OPTION_KEY, $watchlist, false );
    send_stuck_alert( $stuck );
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

    $lines = [
        sprintf(
            /* translators: 1: site name, 2: site URL */
            __( 'The following update%s on %s (%s) appear to have stalled:', 'pie-custom-functions' ),
            $count > 1 ? 's' : '',
            $site_name,
            $site_url
        ),
        '',
    ];

    foreach ( $stuck as $key => $entry ) {
        $started  = wp_date( 'Y-m-d H:i:s T', $entry['started_at'] );
        $duration = human_time_diff( $entry['started_at'] );

        $lines[] = '  ' . sprintf( '%s (%s)', $entry['name'], ucfirst( $entry['type'] ) );
        $lines[] = '  ' . sprintf( __( 'Slug:     %s', 'pie-custom-functions' ), $key );
        $lines[] = '  ' . sprintf( __( 'Started:  %s', 'pie-custom-functions' ), $started );
        $lines[] = '  ' . sprintf( __( 'Duration: %s', 'pie-custom-functions' ), $duration );
        $lines[] = '';
    }

    $lines[] = __( 'The server may have crashed mid-update. Check for partially-extracted files and clear any update locks if needed.', 'pie-custom-functions' );
    $lines[] = '';
    /* translators: %s: admin area URL */
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
