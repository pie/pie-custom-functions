<?php 

/**
 * Plugin Name: Pie Custom Functions MU Plugin
 * Description: Custom functions for Pie Hosting
 * Author: Pie Hosting
 * Author URI: https://pie.co.de
 * Version: 1.3.0
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

// Add the 'Pie Admin' role to any existing user that has an email address ending in '@pie.co.de'
$pie_admins = get_option( 'pie_wpmu_admin_users' );
if ( ! empty( $pie_admins ) ) {
    define( 'WPMUDEV_LIMIT_TO_USER', implode( ',', $pie_admins ) );
}

/**
 * Add the 'Pie Admin' role to any user that registers with an email address ending in '@pie.co.de'
 * 
 * @param int $user_id
 * @return void
 */
function add_pie_admin_role_to_user_pie_email( $user_id ) {
    $user  = get_userdata( $user_id );
    $email = $user->user_email;
    $email = explode( '@', $email );
    $email = $email[1];

    if ( 'pie.co.de' === $email ) {
        $user->add_role( 'pie_admin' );
    }
}
add_action( 'user_register', __NAMESPACE__ . '\add_pie_admin_role_to_user_pie_email' );

/**
 * Remove the 'Brand Pro' and 'Pie Custom Functions' plugins from the plugins page for all users except Pie Admin
 * 
 * @param array $plugins
 * @return array $plugins
 */
function hide_plugins_on_plugins_page( $plugins ) {
    $current_user = wp_get_current_user();

    if ( ! in_array( 'pie_admin', $current_user->roles ) ) {
        if ( isset( $plugins['ultimate-branding/ultimate-branding.php'] ) ) {
             unset( $plugins['ultimate-branding/ultimate-branding.php'] );
        }
        if ( isset( $plugins['pie-custom-functions/pie-custom-functions.php'] ) ) {
             unset( $plugins['pie-custom-functions/pie-custom-functions.php'] );
        }
    }

    return $plugins;
}
add_filter( 'all_plugins', __NAMESPACE__ . '\hide_plugins_on_plugins_page' );

/**
 * Remove the 'Ultimate Branding' tips post type and 'Ultimate Branding' menu item from the admin menu for all users except Pie Admin
 * 
 * @return void
 */
function hide_plugins_from_side_bar() {
    $current_user = wp_get_current_user();

    if ( ! in_array( 'pie_admin', $current_user->roles ) ) {
        add_filter( 'branda_permissions_allowed_roles', '__return_empty_array' );
        remove_menu_page( 'edit.php?post_type=admin_panel_tip' );
        remove_menu_page( 'wpmudev-videos' );
    }
}
add_action( 'admin_menu', __NAMESPACE__ . '\hide_plugins_from_side_bar' );
add_action( 'network_admin_menu', __NAMESPACE__ . '\hide_plugins_from_side_bar' );

/**
 * Add a meta box to the user profile page to allow the user to select a custom role
 * This box is only to be shown on Multisite as a user must be a super admin with the 'pie_admin' role added as an additional role
 * 
 * @return void
 */
if ( is_multisite() ) {

    // Staging setup, check the url and if it contains the word staging then begin staging setup
    // Always set to staging as WPMU DEV has staging setup for all sites
    if ( strpos( $_SERVER['HTTP_HOST'], 'staging.tempurl.host' ) !== false ) {
        add_action( 'admin_init', __NAMESPACE__ . '\update_domain_mapping');
    }

    /**
     * Update the domain mapping for all sites on the multisite network
     * 
     * For example if the main site is https://pie.staging.tempurl.host and the staging site is https://.pie.co.de then the staging site will be updated to https://pie.co.de.pie.staging.tempurl.host
     * 
     * TO Do: Check on how get_site_url and other functions work with multisite.
     * 
     * To Do: Ensure that links in network admin are updated to the new domain
     * 
     * @return void
     */
    function update_domain_mapping() {
        $sites         = get_sites();
        $main_site_url = get_site_url( 1 );

        $main_site_url = str_replace( 'http://', '', $main_site_url );
        $main_site_url = str_replace( 'https://', '', $main_site_url );

        foreach( $sites as $site ){
            $site_id  = absint( $site->blog_id );
            $site_url = $site->domain;

            if ( 1 === $site_id || strpos( $site_url, 'staging.tempurl.host' ) ) {
                continue;
            }

            $site_domain = $site_url . '.' . $main_site_url;
            $site_url    = 'https://' . $site_url . '.' . $main_site_url;
            update_blog_details( $site_id, array( 
                'domain' => $site_domain,
                'path'   => '/',
            ));
            update_blog_option( $site_id, 'siteurl', $site_url );
            update_blog_option( $site_id, 'home', $site_url );
        }
    }

    /**
     * Add a custom meta box to the user profile page
     * 
     * @return void
     */
    function custom_user_profile_meta_box() {
        add_action( 'show_user_profile', __NAMESPACE__ . '\custom_user_role_meta_box_callback' );
        add_action( 'edit_user_profile', __NAMESPACE__ . '\custom_user_role_meta_box_callback' );
    }
    add_action( 'admin_init', __NAMESPACE__ . '\custom_user_profile_meta_box' );

    /**
     * Display the custom meta box on the user profile page
     *
     * @param WP_User $user
     * @return void
     */
    function custom_user_role_meta_box_callback( $user ) {
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
     * Set the custom role after the user has been saved
     *
     * @param int $user_id
     * @return void
     */    
    function custom_set_user_role( $user_id ) {
        if ( isset( $_POST['custom_user_role'] ) ) {
            $selected_role = sanitize_key( $_POST['custom_user_role'] );
            $user          = get_userdata( $user_id );
            if ( $selected_role ) {
                $user->add_role( $selected_role );
                update_user_meta( $user_id, 'custom_user_role', $selected_role );
            } else {
                $user->remove_role( $selected_role );
                delete_user_meta( $user_id, 'custom_user_role' );
            }
        }
    }
    add_action( 'profile_update', __NAMESPACE__ . '\custom_set_user_role' );
}
