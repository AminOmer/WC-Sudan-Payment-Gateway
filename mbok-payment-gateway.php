<?php

 /**
 * Plugin Name: Bank of Khartoum Payment Gateway for WooCommerce
 * Plugin URI: https://github.com/AminOmer/mbok-payment-gateway
 * Description: This plugin enables you to add a new payment method for WooCommerce in Sudan through the Bank of Khartoum (mBok application). Users can upload a copy of the bank receipt for the Bank of Khartoum application with the transfer number to your account number (with your name, bank branch and phone number shown). A copy of the notification is uploaded to the checkout page when submitting the application, then the application is placed in a "processing" status until it is reviewed by the site's admins and the application is completed.
 * Version: 1.0.0
 * Requires at least: 5.5
 * Requires PHP: 7.0
 * Author: Amin Omer
 * Author URI: https://AminOmer.com/
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

defined('ABSPATH') or exit;

// Define MBOK_PLUGIN_DIR.
if ( ! defined( 'MBOK_PLUGIN_DIR' ) ) {
	define( 'MBOK_PLUGIN_DIR', dirname(__FILE__) );
}

// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}


// load mBok payment gateway
add_action('plugins_loaded', 'mbok_gateway_init', 11);

function mbok_gateway_init()
{
    require_once(MBOK_PLUGIN_DIR . '/classes/mbok_payment_gateway.php');
}

require_once(MBOK_PLUGIN_DIR . '/includes/hooks.php');
