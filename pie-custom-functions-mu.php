<?php 

/**
 * Plugin Name: Pie Custom Functions MU Plugin
 * Description: Custom functions for Pie Hosting
 * Author: Pie Hosting
 * Author URI: https://pie.co.de
 * Version: 1.2.2
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