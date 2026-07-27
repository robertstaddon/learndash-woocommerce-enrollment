<?php
/**
 * Plugin Name:       LearnDash WooCommerce Product Enrollment
 * Plugin URI:        https://github.com/robertstaddon/learndash-woocommerce-enrollment
 * Description:       Adds a WooCommerce Product enrollment mode to LearnDash courses with a product selector and checkout enrollment link.
 * Version:           1.0.1
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Abundant Designs
 * Author URI:        https://www.abundantdesigns.com
 * Text Domain:       learndash-woocommerce-enrollment
 * Domain Path:       /languages
 * Update URI:        https://manage.abundantdesigns.com/wp-json/update-server/learndash-woocommerce-enrollment/
 * WC requires at least: 7.0
 * WC tested up to:   9.6
 *
 * @package LearnDash_WooCommerce_Enrollment
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LDWC_ENROLLMENT_VERSION', '1.0.1' );
define( 'LDWC_ENROLLMENT_FILE', __FILE__ );
define( 'LDWC_ENROLLMENT_PATH', plugin_dir_path( __FILE__ ) );

require_once LDWC_ENROLLMENT_PATH . 'includes/class-ldwc-enrollment.php';

register_activation_hook( LDWC_ENROLLMENT_FILE, 'ldwc_enrollment_activate' );

/**
 * Block activation when LearnDash or WooCommerce is missing.
 */
function ldwc_enrollment_activate(): void {
	if ( ldwc_enrollment_dependencies_met() ) {
		return;
	}

	$missing = ldwc_enrollment_get_missing_dependencies();

	deactivate_plugins( plugin_basename( LDWC_ENROLLMENT_FILE ) );

	wp_die(
		esc_html(
			sprintf(
				/* translators: %s: comma-separated plugin names */
				__( 'LearnDash WooCommerce Product Enrollment requires %s to be installed and active.', 'learndash-woocommerce-enrollment' ),
				implode( ', ', $missing )
			)
		),
		esc_html__( 'Plugin Activation Error', 'learndash-woocommerce-enrollment' ),
		array( 'back_link' => true )
	);
}

/**
 * Bootstrap plugin after LearnDash and WooCommerce have loaded.
 */
function ldwc_enrollment_init(): void {
	if ( ! ldwc_enrollment_dependencies_met() ) {
		add_action( 'admin_notices', 'ldwc_enrollment_missing_dependencies_notice' );
		return;
	}

	new LDWC_Enrollment();
}
add_action( 'plugins_loaded', 'ldwc_enrollment_init', 20 );

/**
 * Whether LearnDash and WooCommerce are available.
 */
function ldwc_enrollment_dependencies_met(): bool {
	return empty( ldwc_enrollment_get_missing_dependencies() );
}

/**
 * Names of required plugins that are not active.
 *
 * @return string[]
 */
function ldwc_enrollment_get_missing_dependencies(): array {
	$missing = array();

	if ( ! ldwc_enrollment_is_learndash_active() ) {
		$missing[] = __( 'LearnDash LMS', 'learndash-woocommerce-enrollment' );
	}

	if ( ! class_exists( 'WooCommerce' ) ) {
		$missing[] = __( 'WooCommerce', 'learndash-woocommerce-enrollment' );
	}

	return $missing;
}

/**
 * Whether LearnDash LMS is active.
 *
 * LearnDash is not on WordPress.org, so it cannot be listed in Requires Plugins.
 * Detect it by constants, main class, or plugin basename (sfwd-lms/sfwd_lms.php).
 */
function ldwc_enrollment_is_learndash_active(): bool {
	if ( defined( 'LEARNDASH_VERSION' ) || defined( 'LEARNDASH_LMS_PLUGIN_KEY' ) ) {
		return true;
	}

	if ( class_exists( 'SFWD_LMS' ) ) {
		return true;
	}

	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	return is_plugin_active( 'sfwd-lms/sfwd_lms.php' );
}

/**
 * Admin notice when required plugins are missing.
 */
function ldwc_enrollment_missing_dependencies_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$missing = ldwc_enrollment_get_missing_dependencies();

	if ( empty( $missing ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: %s: comma-separated plugin names */
				__( 'LearnDash WooCommerce Product Enrollment requires %s to be installed and active.', 'learndash-woocommerce-enrollment' ),
				implode( ', ', $missing )
			)
		)
	);
}
