<?php 
/*
Plugin Name: Pie Plugin Template
Description: This plugin doesn't do anything, but gives you a very minimal starting template.
Version: 0.0.1
Author: The team at PIE
Author URI: https://pie.co.de
*/

namespace PIE\PluginTemplate;

/**
 * Load Composer autoloader
 */
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$update_checker = PucFactory::buildUpdateChecker(
    'https://pie.github.io/plugin_template/update.json',
    __FILE__,
    'plugin_template'
);