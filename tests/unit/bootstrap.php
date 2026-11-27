<?php
/**
 * Bootstrap for the unit tests: no WordPress, no database. WordPress and WooCommerce functions
 * that the code under test calls are stubbed per test with Brain Monkey (TestCase sets the
 * common ones). Only files whose top level defines functions, classes and constants can be
 * loaded here; a file that calls WordPress while loading needs a stub first.
 *
 * @package pie-custom-functions
 */

require_once dirname( __DIR__, 2 ) . '/tools/vendor/autoload.php';

define( 'ABSPATH', '/tmp/wordpress/' );
define( 'TESTS_CODE_DIR', dirname( __DIR__, 2 ) . '/plugins/pie-custom-functions' );

// Constants the code under test expects, which are normally defined by a file that hooks into
// WordPress as it loads, go here, e.g. define( 'MYPLUGIN_VERSION', '1.0.0' ); (a namespaced
// constant takes its namespace: define( 'MyPlugin\VERSION', '1.0.0' );)

require_once __DIR__ . '/TestCase.php';

// The files under test, e.g. require_once TESTS_CODE_DIR . '/inc/business-rules.php';
