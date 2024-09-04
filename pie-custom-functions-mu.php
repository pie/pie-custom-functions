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

// Add custom user role for Pie Admin
function add_pie_admin_role()
{
    remove_role('pie_admin');
    add_role('pie_admin', 'Pie Admin', get_role('administrator')->capabilities);
}
// Hook the function into the 'init' action with a lower priority
add_action('init', __NAMESPACE__ . '\add_pie_admin_role', 10);


/**
 * Add the 'Pie Admin' role to any existing user that has an email address ending in '@pie.co.de'
 * This function runs when the plugin is activated
 * 
 * @return void
 */

 function add_pie_admin_role_to_existing_users()
 {
 
     $users = get_users(array(
         'search' => '*@pie.co.de',
         'search_columns' => array('user_email'),
     ));
 
     foreach ($users as $user) {
         assign_pie_admin_to_user($user);
     }
 }
 add_action('init', __NAMESPACE__ . '\add_pie_admin_role_to_existing_users', 10);

/**
 * Add a meta box to the user profile page to allow the user to select a custom role
 * This box is only to be shown on Multisite as a user must be a super admin witht the 'pie_admin' role added as an additional role
 * 
 * @return void
 */
if (is_multisite()) {

    /**
     * Add a custom meta box to the user profile page
     * 
     * @return void
     */
    add_action('admin_init', __NAMESPACE__ . '\custom_user_profile_meta_box');

    function custom_user_profile_meta_box()
    {
        add_action('show_user_profile', __NAMESPACE__ . '\custom_user_role_meta_box_callback');
        add_action('edit_user_profile', __NAMESPACE__ . '\custom_user_role_meta_box_callback');
    }

    /**
     * Display the custom meta box on the user profile page
     *
     * @param [type] $user
     * @return void
     */
    function custom_user_role_meta_box_callback($user)
    {
        $selected_role = get_user_meta($user->ID, 'custom_user_role', true);

        echo '<h3>Custom User Role</h3>';
        echo '<table class="form-table"><tr>';
        echo '<th><label for="custom_user_role">Select Custom User Role:</label></th>';
        echo '<td><select name="custom_user_role" id="custom_user_role">';

        $selected = selected($selected_role, 'pie_admin', false);
        echo "<option value='' $selected>Select Role</option>";
        echo "<option value='pie_admin' $selected>Pie Admin</option>";

        echo '</select></td></tr></table>';
    }

    // Save the selected custom role when the user profile is updated
    add_action('personal_options_update', __NAMESPACE__ . '\custom_save_user_role');
    add_action('edit_user_profile_update', __NAMESPACE__ . '\custom_save_user_role');

    function custom_save_user_role($user_id)
    {
        if (current_user_can('edit_user', $user_id)) {
            $selected_role = sanitize_key($_POST['custom_user_role']);
            update_user_meta($user_id, 'custom_user_role', $selected_role);
        }
    }

    // Set the custom role after the user has been saved
    add_action('profile_update', __NAMESPACE__ . '\custom_set_user_role',);

    function custom_set_user_role($user_id)
    {
        $selected_role = get_user_meta($user_id, 'custom_user_role', true);


        if ($selected_role) {
            $user = get_userdata($user_id);
            $user->add_role($selected_role);
        }

        $user = get_userdata($user_id);
    }
}

/**
 * Add the 'Pie Admin' role to any user that registers with an email address ending in '@pie.co.de'
 * 
 * @param int $user_id
 * @return void
 */

add_action('user_register', __NAMESPACE__ . '\add_pie_admin_role_to_user_pie_email');

function add_pie_admin_role_to_user_pie_email($user_id)
{
    $user = get_userdata($user_id);
    assign_pie_admin_to_user($user);
}



/**
 * Get the current users email address
 * 
 * @param object $user
 * @return string $email
 */

function assign_pie_admin_to_user($user)
{
    $email = $user->user_email;
    $email = explode('@', $email);
    $email = $email[1];
    if ($email == 'pie.co.de') {
        $user->add_role('pie_admin');
    }
}

/**
 * Remove the 'Brand Pro' and 'Pie Custom Functions' plugins from the plugins page for all users except Pie Admin
 * 
 * @param array $plugins
 * @return array $plugins
 */

add_filter('all_plugins', __NAMESPACE__ . '\hide_plugins_on_plugins_page');
function hide_plugins_on_plugins_page($plugins)
{
    $current_user = wp_get_current_user();

    if (!in_array('pie_admin', $current_user->roles)) {
        if (isset($plugins['ultimate-branding/ultimate-branding.php'])) {
            unset($plugins['ultimate-branding/ultimate-branding.php']);
        }
        if (isset($plugins['pie-custom-functions/pie-custom-functions.php'])) {
            unset($plugins['pie-custom-functions/pie-custom-functions.php']);
        }
    }
    return $plugins;
}

/**
 * Remove the 'Ultimate Branding' tips post type and 'Ultimate Branding' menu item from the admin menu for all users except Pie Admin
 * 
 * @return void
 */
add_action('admin_menu', __NAMESPACE__ . '\hide_plugins_from_side_bar');
add_action('network_admin_menu', __NAMESPACE__ . '\hide_plugins_from_side_bar');
function hide_plugins_from_side_bar()
{
    $current_user = wp_get_current_user();

    if (!in_array('pie_admin', $current_user->roles)) {
        add_filter('branda_permissions_allowed_roles', '__return_empty_array');
        remove_menu_page('edit.php?post_type=admin_panel_tip');
        remove_menu_page('wpmudev-videos');
    }
}


/**
 * Only allow access to the 'WPMU DEV' plugin item for Pie Admin
 * 
 * @return void
 */


$users = get_users(array(
    'role' => 'pie_admin'
));

$pie_admins = wp_list_pluck($users, 'ID');


if (!empty($pie_admins)) {
    define('WPMUDEV_LIMIT_TO_USER', implode(',', $pie_admins));
}


/**
 * Staging setup, check the url and if it contains the word staging then begin staging setup
 * Always set to staging as WPMU DEV has staging setup for all sites
 * 
 * @return void
 */

add_action('admin_init', __NAMESPACE__ . '\staging_setup');
function staging_setup(){

    // Before performing checks, ensure there is a live site url, if not, assume this is the live site
    set_duplicate_site_url_lock();

    if (is_staging_site()) {
        if( is_multisite()) {
            update_domain_mapping();
        }
    }
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


function update_domain_mapping(){
    $sites = get_sites();
    $main_site_url = get_site_url(1);

    $main_site_url = str_replace('http://', '', $main_site_url);
    $main_site_url = str_replace('https://', '', $main_site_url);
    foreach($sites as $site){
        $site_id = $site->blog_id;
        $site_url = $site->domain;
        if('1' == $site_id || strpos($site_url, 'staging.tempurl.host') ){
            continue;
        }
        $site_domain = $site_url . '.' . $main_site_url;
        $site_url = 'https://' . $site_url . '.' . $main_site_url;
        update_blog_details($site_id, array('domain' => $site_domain, 'path' => '/'));

        update_blog_option($site_id, 'siteurl', $site_url);
        update_blog_option($site_id, 'home', $site_url);
    }
}


/**
 * 
 *  Compares known live site url and curent url to check if this is a staging site
 * 
 */

 
function set_duplicate_site_url_lock() {

    // Add option does not overwrite options that are already set
    add_option( 'pcf_siteurl', get_duplicate_site_lock_key() );

}

function get_duplicate_site_lock_key() {

    // Grabs site url from current site
    $site_url = get_site_url( 'current_wp_site' );

    // Inserts constant into url to ensure no search and replace done to staging will affect it and returns the value
    return substr_replace(
        $site_url,
        '_[pcf_site_url]_',
        intval( strlen( $site_url ) / 2 ),
        0
    );
}

function is_staging_site() {

    // Gets both current site url and known live site url
    $pcf_current_site_url = get_option( 'siteurl' );
    $pcf_live_site_url = get_option( 'pcf_siteurl' );

    // Remove constant from saved live site url to produce accurate live site url
    $live_site_url = str_replace('_[pcf_site_url]_', '', $pcf_live_site_url);

    // Compare strings of current site and known live site, returns bool
    if ( $live_site_url === $pcf_current_site_url ) {
        return false;
    } else {
        return true;
    }

}
