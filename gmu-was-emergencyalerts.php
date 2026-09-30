<?php
/**
 * Plugin Name:   GMU Emergency Alerts
 * Description:   Display official GMU emergency alerts in a banner at the top of a WordPress site.
 * Version:       1.0.0
 * Author:        ITS Web Services, George Mason University
 * Author URI:    https://its.gmu.edu
 * Text Domain:   gmu-was-emergencyalerts
 *
 * @package       GMU_WAS_EMERGENCYALERTS
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

date_default_timezone_set( 'America/New_York' );

// Plugin Update Checker.
require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
$update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/mason-its-web/gmu-was-emergencyalerts',
	__FILE__,
	'gmu-was-emergencyalerts'
);

require 'src/GMUActiveAlerts.php';
require 'src/GMUAlertsSettingsForm.php';

$alerts        = new GMUActiveAlerts();
$settings_form = new GMUAlertsSettingsForm();

add_action( 'wp_enqueue_scripts', array( $alerts, 'enqueueAssets' ) );

add_action( 'wp_ajax_gmu_get_active_alert', array( $alerts, 'ajaxDisplayAlert' ) );
add_action( 'wp_ajax_nopriv_gmu_get_active_alert', array( $alerts, 'ajaxDisplayAlert' ) );

add_action( 'wp_body_open', array( $alerts, 'displayAlertPlaceholder' ) );


// Settings page functions.
add_action(
    'admin_menu',
    array( $settings_form, 'gmu_was_emergencyalerts_add_settings_page' )
);

add_action(
    'admin_init',
    array( $settings_form, 'gmu_was_emergencyalerts_register_settings' )
);

// Settings link on plugins page.
add_filter(
    'plugin_action_links_' . plugin_basename( __FILE__ ),
    array( $settings_form, 'gmu_was_emergencyalerts_plugin_action_links' )
);
