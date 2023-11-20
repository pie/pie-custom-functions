<?php 

/**
 * Plugin Name: Pie Custom Functions
 * Description: Custom functions for Pie Hosting
 * Author: Pie Hosting
 * Author URI: https://pie.co.de
 * Version: 1.0.0
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pie-custom-functions
 * Domain Path: /languages
 *  
 */

namespace PieCustomFunctions;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Add custom user role for Pie Admin
add_role('pie_admin', 'Pie Admin', get_role( 'administrator' )->capabilities );


/**
 * Remove the 'Brand Pro' and 'Pie Custom Functions' plugins from the plugins page for all users except Pie Admin
 * 
 * @param array $plugins
 * @return array $plugins
 */

add_filter('all_plugins', __NAMESPACE__ . '\hide_plugins');
function hide_plugins($plugins){
    if(!current_user_can('pie_admin')){
        if(isset($plugins['ultimate-branding/ultimate-branding.php'])){
            unset($plugins['ultimate-branding/ultimate-branding.php']);
        }
        if(isset($plugins['pie-custom-functions/pie-custom-functions.php'])){
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
function hide_plugins_from_side_bar(){
    if(!current_user_can('pie_admin')){
        add_filter('branda_permissions_allowed_roles', '__return_empty_array');
        remove_menu_page('edit.php?post_type=admin_panel_tip');
    }
}


/**
 * Only allow access to the 'WPMU DEV' plugin item for Pie Admin
 * 
 * @return void
 */


$users = get_users();

foreach ($users as $user) {
    if (in_array('pie_admin', $user->roles)) {
        $pie_admins[] = $user;
    }
}

$pie_admins = wp_list_pluck( $pie_admins, 'ID' );

define( 'WPMUDEV_LIMIT_TO_USER', $pie_admins );