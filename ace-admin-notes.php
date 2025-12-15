<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://acewebx.com/
 * @since             1.0.0
 * @package           Ace_Admin_Notes
 *
 * @wordpress-plugin
 * Plugin Name:       Ace Admin Notes
 * Plugin URI:        https://acewebx.com/our-products/
 * Description:       This is a description of the plugin.
 * Version:           1.0.0
 * Author:            Acewebx
 * Author URI:        https://acewebx.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ace-admin-notes
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! defined( 'WPINC' ) ) {
	die;
}


define( 'ACE_ADMIN_NOTES_VERSION', '1.0.0' );


function ace_admin_notes_activate_() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-ace-admin-notes-activator.php';
	Ace_Admin_Notes_Activator::activate();
}


function ace_admin_notes_deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-ace-admin-notes-deactivator.php';
	Ace_Admin_Notes_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'ace_admin_notes_activate_' );
register_deactivation_hook( __FILE__, 'ace_admin_notes_deactivate' );


require plugin_dir_path( __FILE__ ) . 'includes/class-ace-admin-notes.php';


function ace_admin_notes_run() {

	$plugin = new Ace_Admin_Notes();
	$plugin->run();

}
ace_admin_notes_run();

