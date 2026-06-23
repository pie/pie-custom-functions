<?php 

/**
 * Plugin Name: PIE Hosting Companion (MU)
 * Description: Essential PIE hosting functionality including URL redirections system, plugin visibility management, multisite user role controls, automatic user role assignment for @pie.co.de emails, and staging domain mapping for multisite networks.
 * Author: The team at PIE
 * Author URI: https://pie.co.de
 * Version: 1.6.0
 * Requires PHP: 8.0
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pie-custom-functions
 * Domain Path: /languages
 *  
 */

namespace Pie\CustomFunctionsMUPlugin;

use function Pie\Utilities\pie_hide_plugin;

//exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Require PHP 8.0 or higher
if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo sprintf(
            /* translators: 1: Plugin name, 2: Required PHP version, 3: Current PHP version */
            __( '%1$s requires PHP %2$s or higher. You are running PHP %3$s.', 'pie-custom-functions' ),
            '<strong>PIE Hosting Companion (MU)</strong>',
            '8.0',
            PHP_VERSION
        );
        echo '</p></div>';
    } );
    return;
}

include_once plugin_dir_path( __FILE__ ) . 'pie/redirections.php';
include_once plugin_dir_path( __FILE__ ) . 'pie/utilities.php';
include_once plugin_dir_path( __FILE__ ) . 'pie/branda-config.php';
include_once plugin_dir_path( __FILE__ ) . 'pie/security-headers.php';
include_once plugin_dir_path( __FILE__ ) . 'pie/pie-admin-access.php';

pie_hide_plugin( 'pie-custom-functions/pie-custom-functions.php' );

/**
 * Remove the legacy pie_admin role and strip it from all users.
 *
 * One-time migration from the role-based approach to the email-based cap grant
 * introduced in 1.5.2. Safe to delete once all sites have run this migration.
 *
 * @since 1.5.2
 * @deprecated Can be removed in a future version once pie_admin_role_cleanup_done is set everywhere.
 */
function migrate_remove_pie_admin_role(): void {
    if ( get_option( 'pie_admin_role_cleanup_done' ) ) {
        return;
    }

    $users = get_users( array( 'role' => 'pie_admin' ) );
    foreach ( $users as $user ) {
        $user->remove_role( 'pie_admin' );
    }

    if ( get_role( 'pie_admin' ) ) {
        remove_role( 'pie_admin' );
    }

    update_option( 'pie_admin_role_cleanup_done', true );
}
add_action( 'init', __NAMESPACE__ . '\migrate_remove_pie_admin_role', 10 );

/**
 * Dynamically grant administrator capabilities to any @pie.co.de user.
 *
 * This replaces the previous role-based approach — no role is created or
 * assigned. Capabilities are computed at runtime from the user's email.
 *
 * @since 1.5.2
 */
function grant_pie_admin_caps( array $allcaps, array $_caps, array $_args, \WP_User $user ): array {
    if ( ! is_pie_admin( $user->ID ) ) {
        return $allcaps;
    }

    $admin_role = get_role( 'administrator' );
    if ( $admin_role ) {
        foreach ( $admin_role->capabilities as $cap => $grant ) {
            $allcaps[ $cap ] = $grant;
        }
    }

    return $allcaps;
}
add_filter( 'user_has_cap', __NAMESPACE__ . '\grant_pie_admin_caps', 10, 4 );

/**
 * Check whether an email address belongs to PIE.
 *
 * @since 1.5.2
 * @param string $email
 * @return bool
 */
function is_pie_admin_email( string $email ): bool {
    return str_ends_with( $email, '@pie.co.de' );
}

/**
 * Check whether a user is a Pie admin.
 *
 * A user qualifies if they have a @pie.co.de email address or the
 * pie_admin_override meta flag set by a PIE admin. The result is filterable
 * so hosting logic can override the check when needed.
 *
 * @since 1.4.0
 * @param int $user_id Optional. Defaults to the current user.
 * @return bool
 */
function is_pie_admin( int $user_id = 0 ): bool
{
    $user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();

    if ( ! $user instanceof \WP_User || $user->ID < 1 ) {
        return false;
    }

    $is_admin = is_pie_admin_email( $user->user_email )
        || (bool) get_user_meta( $user->ID, 'pie_admin_override', true );

    return (bool) apply_filters(
        'pie_hosting_companion_is_pie_admin',
        $is_admin,
        $user
    );
}

/**
 * Limit WPMU DEV plugin access to @pie.co.de users only.
 *
 * @since 1.0.0
 */
$users = get_users( array(
    'search'         => '*@pie.co.de',
    'search_columns' => array( 'user_email' ),
) );

// wp_list_pluck always returns an array
$pie_admins = wp_list_pluck( $users, 'ID' );

if ( count( $pie_admins ) > 0 ) {
    define( 'WPMUDEV_LIMIT_TO_USER', implode( ',', $pie_admins ) );
}


/**
 * Staging site setup and configuration
 * 
 * Performs staging site detection and applies appropriate configurations
 * including spam filtering and domain mapping for multisite networks.
 * 
 * @since 1.0.0
 */

// Before performing checks, ensure there is a live site url, if not, assume this is the live site
set_duplicate_site_url_lock();

if ( is_staging_site() ) {

    // Marks all WPCF7 forms as not spam if on staging site
    add_filter( 'wpcf7_spam', '__return_false' );

    // Hooks into admin_init for code specific to the area on staging sites
    add_action( 'admin_init', __NAMESPACE__ . '\staging_setup' );
    
}

/**
 * Configure staging site specific settings
 * 
 * Handles multisite domain mapping updates for staging environments.
 * Currently applies domain mapping fixes for staging.tempurl.host environments.
 * 
 * @since 1.0.0
 * @return void
 */
function staging_setup(): void
{
    if ( is_multisite() ) {
        // Temporary fix to avoid issues with staging on multisite
        if ( false !== strpos( $_SERVER['HTTP_HOST'], 'staging.tempurl.host' ) ) {
            update_domain_mapping();
        }
    }
}

/**
 * Update the domain mapping for all sites on the multisite network
 * 
 * Maps staging domains to production-style URLs for testing purposes.
 * For example: pie.co.de becomes pie.co.de.pie.staging.tempurl.host
 * 
 * @since 1.0.0
 * @todo Check how get_site_url and other functions work with multisite
 * @todo Ensure that links in network admin are updated to the new domain
 * @return void
 */
function update_domain_mapping(): void
{
    $sites = get_sites();
    $main_site_url = get_site_url( 1 );

    $main_site_url = str_replace( array( 'http://', 'https://' ), '', $main_site_url );
    
    foreach ( $sites as $site ) {
        $site_id = $site->blog_id;
        $site_url = $site->domain;
        
        if ( '1' === $site_id || false !== strpos( $site_url, 'staging.tempurl.host' ) ) {
            continue;
        }
        
        $site_domain = $site_url . '.' . $main_site_url;
        $site_url = 'https://' . $site_url . '.' . $main_site_url;
        
        update_blog_details( $site_id, array(
            'domain' => $site_domain,
            'path' => '/'
        ) );

        update_blog_option( $site_id, 'siteurl', $site_url );
        update_blog_option( $site_id, 'home', $site_url );
    }
}


/**
 * Set up a protected site URL lock for staging detection
 * 
 * Creates a protected version of the site URL that won't be affected
 * by search and replace operations during staging site creation.
 * 
 * @since 1.0.0
 * @return void
 */
function set_duplicate_site_url_lock(): void
{
    // Add option does not overwrite options that are already set
    add_option( 'pcf_siteurl', get_duplicate_site_lock_key() );
}

/**
 * Generate a protected site URL key for staging detection
 * 
 * Creates a URL with an embedded constant that won't be affected by
 * search and replace operations during staging site deployment.
 * 
 * @since 1.0.0
 * @return string The protected site URL with embedded constant
 */
function get_duplicate_site_lock_key(): string
{
    // Grabs site url from current site
    $site_url = get_site_url();

    // Inserts constant into url to ensure no search and replace done to staging will affect it
    return substr_replace(
        $site_url,
        '_[pcf_site_url]_',
        intval( strlen( $site_url ) / 2 ),
        0
    );
}

/**
 * Determine if current site is a staging environment
 * 
 * Compares the current site URL with the protected live site URL
 * to determine if this is a staging site or the live production site.
 * 
 * @since 1.0.0
 * @return bool True if this is a staging site, false if live site
 */
function is_staging_site(): bool
{
    // Gets both current site url and known live site url
    $pcf_current_site_url = get_option( 'siteurl' );
    $pcf_live_site_url = get_option( 'pcf_siteurl' );

    // Remove constant from saved live site url to produce accurate live site url
    $live_site_url = str_replace( '_[pcf_site_url]_', '', $pcf_live_site_url );

    // Compare strings of current site and known live site, returns bool
    return $live_site_url !== $pcf_current_site_url;
}
