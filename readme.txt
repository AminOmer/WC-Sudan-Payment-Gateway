=== Sudan Payment Gateway for WooCommerce ===
Contributors: AminOmer
Tags: woocommerce, payment gateway, sudan, bank of khartoum, mbok
Requires at least: 5.5
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.2.3
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

== Description ==

Sudan Payment Gateway for WooCommerce enables customers to make payments through Sudanese banking applications such as Bank of Khartoum (mBok) and upload their payment receipt during checkout for manual verification by the store administrator.

The uploaded receipt and transfer number are attached to the WooCommerce order and the order is automatically placed into processing status until the payment is reviewed.

Features:

* WooCommerce payment gateway integration
* Bank transfer receipt upload support
* Transfer number submission
* Manual payment verification workflow
* Mobile-friendly checkout interface
* Arabic language support
* Secure file upload validation
* Compatible with modern WooCommerce versions

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/` directory or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to WooCommerce → Settings → Payments.
4. Enable "Sudan Payment Gateway".
5. Configure your bank account information and save settings.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

Yes. WooCommerce must be installed and activated.

= Which payment methods are supported? =

The plugin supports manual payments through Sudanese banking applications such as Bank of Khartoum (mBok).

= Can customers upload payment receipts? =

Yes. Customers can upload an image of the payment receipt during checkout.

= What file types are allowed? =

JPG, JPEG, PNG, and GIF image formats.

= Is the uploaded file secure? =

Yes. The plugin validates uploaded files and uses secure WordPress upload handling.

= Is the plugin free? =

Yes. The plugin is licensed under GPLv3 or later.

== Screenshots ==

1. WooCommerce payment gateway settings.
2. Checkout payment form.
3. Mobile checkout interface.

== Changelog ==

= 1.2.3 =

* Security improvements for receipt uploads.
* Added nonce verification for AJAX requests.
* Replaced direct file uploads with secure WordPress upload handling.
* Improved file validation and sanitization.
* Compatibility improvements for latest WordPress versions.

= 1.2.2 =

* Some bugs have been fixed.

= 1.2.1 =

* Some bugs have been fixed.

= 1.2.0 =

* User interface improved.

= 1.1.0 =

* Arabic language added.

= 1.0.0 =

* Initial release.

== Upgrade Notice ==

= 1.2.1 =

This update includes important security fixes related to file uploads. Updating immediately is strongly recommended.

== Credits ==

Developed by Amin Omer.