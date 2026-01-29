<?php 

/**
 * Plugin Name: PIE Hosting Companion (MU)
 * Description: Essential PIE hosting functionality including URL redirections system, plugin visibility management, multisite user role controls, automatic user role assignment for @pie.co.de emails, and staging domain mapping for multisite networks.
 * Author: The team at PIE
 * Author URI: https://pie.co.de
 * Version: 1.3.3
 * Requires PHP: 8.0
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pie-custom-functions
 * Domain Path: /languages
 *  
 */

namespace Pie\CustomFunctionsMUPlugin;

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

/**
 * Add custom user role for Pie Admin
 * 
 * Creates a new 'pie_admin' role with administrator capabilities.
 * Removes any existing role with the same name first.
 * 
 * @since 1.0.0
 * @return void
 */
function add_pie_admin_role(): void
{
    remove_role( 'pie_admin' );
    add_role( 'pie_admin', 'Pie Admin', get_role( 'administrator' )->capabilities );
}
// Hook the function into the 'init' action with a lower priority
add_action( 'init', __NAMESPACE__ . '\add_pie_admin_role', 10 );


/**
 * Add the 'Pie Admin' role to any existing user that has an email address ending in '@pie.co.de'
 * 
 * This function runs during plugin initialization and searches for all users
 * with @pie.co.de email addresses, then assigns them the pie_admin role.
 * 
 * @since 1.0.0
 * @return void
 */
function add_pie_admin_role_to_existing_users(): void
{
    $users = get_users( array(
        'search' => '*@pie.co.de',
        'search_columns' => array( 'user_email' ),
    ) );

    foreach ( $users as $user ) {
        assign_pie_admin_to_user( $user );
    }
}
add_action( 'init', __NAMESPACE__ . '\add_pie_admin_role_to_existing_users', 10 );

/**
 * Add a meta box to the user profile page to allow the user to select a custom role
 * This box is only to be shown on Multisite as a user must be a super admin witht the 'pie_admin' role added as an additional role
 * 
 * @return void
 */
if ( is_multisite() ) {

    /**
     * Add a custom meta box to the user profile page
     * 
     * Registers action hooks to display the custom role selection
     * meta box on both user profile editing pages.
     * 
     * @since 1.0.0
     * @return void
     */
    add_action( 'admin_init', __NAMESPACE__ . '\custom_user_profile_meta_box' );

    function custom_user_profile_meta_box(): void
    {
        add_action( 'show_user_profile', __NAMESPACE__ . '\custom_user_role_meta_box_callback' );
        add_action( 'edit_user_profile', __NAMESPACE__ . '\custom_user_role_meta_box_callback' );
    }

    /**
     * Display the custom meta box on the user profile page
     * 
     * Renders a dropdown selection for assigning custom user roles.
     * Currently supports the 'pie_admin' role selection.
     *
     * @since 1.0.0
     * @param \WP_User $user The user object being edited
     * @return void
     */
    function custom_user_role_meta_box_callback( \WP_User $user ): void
    {
        $selected_role = get_user_meta( $user->ID, 'custom_user_role', true );

        echo '<h3>Custom User Role</h3>';
        echo '<table class="form-table"><tr>';
        echo '<th><label for="custom_user_role">Select Custom User Role:</label></th>';
        echo '<td><select name="custom_user_role" id="custom_user_role">';

        $selected = selected( $selected_role, 'pie_admin', false );
        echo "<option value='' $selected>Select Role</option>";
        echo "<option value='pie_admin' $selected>Pie Admin</option>";

        echo '</select></td></tr></table>';
    }

    /**
     * Save the selected custom role when the user profile is updated
     * 
     * Validates user permissions and sanitizes the role selection before
     * saving it to user meta data.
     * 
     * @since 1.0.0
     * @param int $user_id The ID of the user being updated
     * @return void
     */
    add_action( 'personal_options_update', __NAMESPACE__ . '\custom_save_user_role' );
    add_action( 'edit_user_profile_update', __NAMESPACE__ . '\custom_save_user_role' );

    function custom_save_user_role( int $user_id ): void
    {
        if ( current_user_can( 'edit_user', $user_id ) && isset( $_POST['custom_user_role'] ) ) {
            $selected_role = sanitize_key( $_POST['custom_user_role'] );
            update_user_meta( $user_id, 'custom_user_role', $selected_role );
        }
    }

    /**
     * Set the custom role after the user has been saved
     * 
     * Retrieves the selected custom role from user meta and assigns
     * it to the user if a valid role was selected.
     * 
     * @since 1.0.0
     * @param int $user_id The ID of the user being updated
     * @return void
     */
    add_action( 'profile_update', __NAMESPACE__ . '\custom_set_user_role' );

    function custom_set_user_role( int $user_id ): void
    {
        $selected_role = get_user_meta( $user_id, 'custom_user_role', true );

        if ( $selected_role ) {
            $user = get_userdata( $user_id );
            if ( $user ) {
                $user->add_role( $selected_role );
            }
        }
    }
}

/**
 * Add the 'Pie Admin' role to any user that registers with an email address ending in '@pie.co.de'
 * 
 * This function is automatically triggered when a new user registers.
 * It checks their email domain and assigns the pie_admin role if appropriate.
 * 
 * @since 1.0.0
 * @param int $user_id The ID of the newly registered user
 * @return void
 */
add_action( 'user_register', __NAMESPACE__ . '\add_pie_admin_role_to_user_pie_email' );

function add_pie_admin_role_to_user_pie_email( int $user_id ): void
{
    $user = get_userdata( $user_id );
    if ( $user ) {
        assign_pie_admin_to_user( $user );
    }
}



/**
 * Assign pie_admin role to user if they have a @pie.co.de email address
 * 
 * Checks the user's email domain and adds the 'pie_admin' role
 * if the domain matches 'pie.co.de'.
 * 
 * @since 1.0.0
 * @param \WP_User $user The user object to check and potentially assign role to
 * @return void
 */
function assign_pie_admin_to_user( \WP_User $user ): void
{
    $email = $user->user_email;
    $email_parts = explode( '@', $email );
    
    if ( 2 === count( $email_parts ) ) {
        $domain = $email_parts[1];
        if ( 'pie.co.de' === $domain ) {
            $user->add_role( 'pie_admin' );
        }
    }
}

/**
 * Remove specific plugins from the plugins page for all users except Pie Admin
 * 
 * Hides 'Ultimate Branding' and 'PIE Custom Functions' plugins from the plugins
 * list for users who don't have the 'pie_admin' role.
 * 
 * @since 1.0.0
 * @param array $plugins Array of all plugins
 * @return array Modified array of plugins with certain plugins potentially removed
 */
add_filter( 'all_plugins', __NAMESPACE__ . '\hide_plugins_on_plugins_page' );

function hide_plugins_on_plugins_page( array $plugins ): array
{
    $current_user = wp_get_current_user();

    if ( ! in_array( 'pie_admin', $current_user->roles, true ) ) {
        if ( isset( $plugins['ultimate-branding/ultimate-branding.php'] ) ) {
            unset( $plugins['ultimate-branding/ultimate-branding.php'] );
        }
        if ( isset( $plugins['pie-custom-functions/pie-custom-functions.php'] ) ) {
            unset( $plugins['pie-custom-functions/pie-custom-functions.php'] );
        }
    }
    
    return $plugins;
}

/**
 * Remove Ultimate Branding menu items from admin sidebar for non-Pie Admin users
 * 
 * Hides Ultimate Branding related menu items and tips post type from the admin
 * sidebar for users who don't have the 'pie_admin' role.
 * 
 * @since 1.0.0
 * @return void
 */
add_action( 'admin_menu', __NAMESPACE__ . '\hide_plugins_from_side_bar' );
add_action( 'network_admin_menu', __NAMESPACE__ . '\hide_plugins_from_side_bar' );

function hide_plugins_from_side_bar(): void
{
    $current_user = wp_get_current_user();

    if ( ! in_array( 'pie_admin', $current_user->roles, true ) ) {
        add_filter( 'branda_permissions_allowed_roles', '__return_empty_array' );
        remove_menu_page( 'edit.php?post_type=admin_panel_tip' );
        remove_menu_page( 'wpmudev-videos' );
    }
}

/**
 * Limit WPMU DEV plugin access to pie_admin users only
 * 
 * Restricts WPMU DEV plugin functionality to users with the 'pie_admin' role
 * by setting the WPMUDEV_LIMIT_TO_USER constant with pie admin user IDs.
 * 
 * @since 1.0.0
 */
$users = get_users( array(
    'role' => 'pie_admin'
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
