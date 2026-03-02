<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://example.com
 * @since             1.0.0
 * @package           Wpragbot
 *
 * @wordpress-plugin
 * Plugin Name:       WPRAGBot
 * Plugin URI:        https://example.com/wpragbot
 * Description:       AI-powered chatbot plugin with RAG integration for Gemini and Qdrant
 * Version:           1.0.0
 * Author:            Your Name
 * Author URI:        https://example.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wpragbot
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 */
define( 'WPRAGBOT_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wpragbot-activator.php
 */
function activate_wpragbot() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpragbot-activator.php';
    Wpragbot_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wpragbot-deactivator.php
 */
function deactivate_wpragbot() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpragbot-deactivator.php';
    Wpragbot_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wpragbot' );
register_deactivation_hook( __FILE__, 'deactivate_wpragbot' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpragbot.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_wpragbot() {
    $plugin = new Wpragbot();
    $plugin->run();
}
run_wpragbot();