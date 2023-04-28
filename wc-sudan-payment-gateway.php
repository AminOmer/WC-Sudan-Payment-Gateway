<?php

/**
 * Plugin Name: Sudan Payment Gateway for WooCommerce by Amin Omer
 * Plugin URI: https://github.com/AminOmer/Amin-Sudan-Payment-Gateway-for-WooCommerce
 * Description: Sudan Payment Gateway for WooCommerce by Amin Omer" is a payment plugin that enables customers to make payments through Sudan's Bank of Khartoum using the mBok application. Customers can upload a copy of the bank receipt with the transfer number to the checkout page, which will then be placed in a "processing" status until it is reviewed and completed by site admins. This plugin is easy to install and configure, and it provides a convenient payment solution for Sudanese businesses using WooCommerce.
 * Version: 1.0.0
 * Requires at least: 5.5
 * Requires PHP: 7.0
 * Author: Amin Omer
 * Author URI: https://AminOmer.com/
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

defined('ABSPATH') or exit;

// Define "Sudan Payment Gateway" plguin dir
if (!defined('SUPG_PLUGIN_DIR')) {
    define('SUPG_PLUGIN_DIR', dirname(__FILE__));
}

// Define "Sudan Payment Gateway" plguin url
if (!defined('SUPG_PLUGIN_URL')) {
    define('SUPG_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Make sure WooCommerce is active.
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// load "Sudan Payment Gateway" plugin
if (!function_exists('sugp_plugin_init')) {
    function sugp_plugin_init()
    {
        require_once(SUPG_PLUGIN_DIR . '/includes/class-plugin-core.php');
    }
}
add_action('plugins_loaded', 'sugp_plugin_init', 11);