<?php

/**
 * Plugin Name: Sudan Payment Gateway for WooCommerce
 * Plugin URI: https://github.com/AminOmer/WC-Sudan-Payment-Gateway
 * Description: Sudan Payment Gateway for WooCommerce enables customers to pay through Sudanese bank applications and upload a bank receipt with the transfer number during checkout for admin review.
 * Version: 1.2.4
 * Requires at least: 5.5
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Author: Amin Omer
 * Author URI: https://AminOmer.com/
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wc-sudan-gateway
 * Requires Plugins: woocommerce
 */

defined('ABSPATH') or exit;

// Define "Sudan Payment Gateway" plugin dir
if (!defined('SUPG_PLUGIN_DIR')) {
    define('SUPG_PLUGIN_DIR', dirname(__FILE__));
}

// Define "Sudan Payment Gateway" plugin url
if (!defined('SUPG_PLUGIN_URL')) {
    define('SUPG_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Make sure WooCommerce is active.
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}


// load "Sudan Payment Gateway" plugin
if (!function_exists('supg_plugin_init')) {
    function supg_plugin_init()
    {
        require_once(SUPG_PLUGIN_DIR . '/includes/class-plugin-core.php');
        load_plugin_textdomain( 'wc-sudan-gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }
}
add_action('plugins_loaded', 'supg_plugin_init', 11);