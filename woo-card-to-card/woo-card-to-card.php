<?php
/**
 * Plugin Name: درگاه پرداخت کارت به کارت (WooCommerce)
 * Plugin URI:  https://github.com/Melika21/woo-card-to-card
 * Description: افزونه درگاه پرداخت کارت به کارت برای ووکامرس — مشتریان شماره کارت و نام صاحب حساب را می‌بینند و پس از واریز، کد پیگیری را وارد می‌کنند.
 * Version:     1.0.0
 * Author:      Melika Sohrabi
 * Author URI:  https://github.com/Melika21
 * License:     GPL-2.0-or-later
 * Text Domain: woo-card-to-card
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 *
 * @package Woo_Card_To_Card
 */

defined( 'ABSPATH' ) || exit;

define( 'WOO_C2C_VERSION', '1.0.0' );
define( 'WOO_C2C_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WOO_C2C_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check WooCommerce dependency.
 */
function woo_c2c_check_woocommerce() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'woo_c2c_missing_wc_notice' );
		return false;
	}
	return true;
}

function woo_c2c_missing_wc_notice() {
	echo '<div class="error"><p><strong>افزونه کارت به کارت</strong> برای کارکرد نیاز به ووکامرس دارد.</p></div>';
}

/**
 * Initialise after WooCommerce is loaded.
 */
add_action( 'plugins_loaded', 'woo_c2c_init', 11 );

function woo_c2c_init() {
	if ( ! woo_c2c_check_woocommerce() ) {
		return;
	}

	require_once WOO_C2C_PLUGIN_DIR . 'includes/class-wc-gateway-card-to-card.php';

	add_filter( 'woocommerce_payment_gateways', 'woo_c2c_add_gateway' );
}

function woo_c2c_add_gateway( $gateways ) {
	$gateways[] = 'WC_Gateway_Card_To_Card';
	return $gateways;
}

/**
 * Quick-settings link on Plugins page.
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'woo_c2c_action_links' );

function woo_c2c_action_links( $links ) {
	$url  = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=card_to_card' );
	$link = '<a href="' . esc_url( $url ) . '">تنظیمات</a>';
	array_unshift( $links, $link );
	return $links;
}

/**
 * Activation — seed default options.
 */
register_activation_hook( __FILE__, 'woo_c2c_activate' );

function woo_c2c_activate() {
	$defaults = array(
		'woo_c2c_title'       => 'پرداخت کارت به کارت',
		'woo_c2c_description' => 'پرداخت از طریق انتقال وجه کارت به کارت',
		'woo_c2c_card_number' => '',
		'woo_c2c_card_holder' => '',
		'woo_c2c_bank_name'   => '',
		'woo_c2c_instructions' => 'لطفاً مبلغ سفارش را به شماره کارت زیر واریز کنید و کد پیگیری را در فرم زیر وارد نمایید.',
	);
	foreach ( $defaults as $key => $value ) {
		if ( ! get_option( $key ) ) {
			update_option( $key, $value );
		}
	}
}

/**
 * Load text domain for translations.
 */
add_action( 'init', 'woo_c2c_load_textdomain' );

function woo_c2c_load_textdomain() {
	load_plugin_textdomain( 'woo-card-to-card', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
