<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('SUPG_Plugin')) {
    class SUPG_Plugin
    {
        function __construct()
        {
            // Adding new WooCommerce Payment gateway
            require_once(SUPG_PLUGIN_DIR . '/includes/wc-sudan-payment-gateway.php');
            add_filter('woocommerce_payment_gateways', array($this, 'wc_add_gateways'));
            
            // do woocommerce ajax
            add_action('wp_ajax_invoice_response', array($this, 'save_receipt_image'));
            add_action('wp_ajax_nopriv_invoice_response', array($this, 'save_receipt_image'));
            
            // woocommerce actions    
            add_action('woocommerce_checkout_update_order_meta', array($this, 'update_order_meta'));
            add_action('woocommerce_admin_order_data_after_order_details', array($this, 'custom_checkout_field_display_admin_order_meta'), 10, 1);
        
            // plugin links
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_links'));
            
            // enqueue checkout scripts
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

            // enqueue admin scripts
            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));

            // Admin orders tabel in woocommerce
            add_filter('manage_edit-shop_order_columns', array($this, 'orders_posts_add_columns'), 20);
            add_action('manage_shop_order_posts_custom_column', array($this, 'orders_posts_column_content'));

            // all plugin settings link
            add_filter('plugin_action_links', array($this, 'add_plugin_settings_link'), 10, 2);
        }

        function wc_add_gateways($gateways)
        {
            $gateways[] = 'WC_Sudan_Payment_Gateway';
            return $gateways;
        }


        /**
         * Adds plugin page links
         */
        function plugin_links($links)
        {

            $plugin_links = array(
                '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=sudan_payment_gateway') . '">' . __('Configure', 'wc-gateway-sudan') . '</a>'
            );

            return array_merge($plugin_links, $links);
        }


        function save_receipt_image()
        {
            
            $upload_dir = wp_upload_dir(); // Get the WordPress upload directory
            $receipt_dir = $upload_dir['basedir'] . '/sudan-payment-receipts'; // Set the directory to save the image
            $receipt_url = $upload_dir['baseurl'] . '/sudan-payments-receipts'; // Receipt url
            if (!file_exists($receipt_dir)) { // Check if the directory exists
                mkdir($receipt_dir, 0755, true); // Create the directory if it doesn't exist
            }

            if (!isset($_FILES['file'])) {
                echo 'Error: $_FILES[file] not set';
                exit;
            }

            $file = $_FILES['file']; // Get the uploaded file
            $order_id = rand(1, 200000); //$_POST['order_id']; // Get the order ID

            // Check if the file is an image and its size is not more than 2MB
            $mime_type = mime_content_type($file['tmp_name']);
            $allowed_types = array('image/jpeg', 'image/png', 'image/gif');
            $max_size = 2 * 1024 * 1024; // 2MB
            if (!in_array($mime_type, $allowed_types) || $file['size'] > $max_size) {
                echo 'Error: The file must be an image and its size must not exceed 2MB.';
                exit;
            }

            

            // Check if file is an image again (using image size)
            $file_info = getimagesize($file['tmp_name']);
            if ($file_info === false) {
                // File is not an image
                echo 'Error: File is not an image.';
                exit;
            }


            // Set the file name and move the file to the receipt directory
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $file_name = $order_id . '.' . $file_extension;
            $file_path = $receipt_dir . '/' . $file_name;
            move_uploaded_file($file['tmp_name'], $file_path);

            // Add the file to the WordPress media library
            $attachment = array(
                'guid' => $receipt_url . '/sudan-payments/' . $file_name,
                'post_mime_type' => $mime_type,
                'post_title' => $file_name,
                'post_content' => '',
                'post_status' => 'inherit'
            );
            $attach_id = wp_insert_attachment($attachment, $file_path);
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
            wp_update_attachment_metadata($attach_id, $attach_data);

            // show uploaded attachment ID
            echo esc_attr($attach_id);
            exit;
        }

        function update_order_meta($order_id)
        {
            if ($_POST['payment_method'] != 'sudan_payment_gateway')
                return;

            update_post_meta($order_id, 'trx_number', sanitize_text_field($_POST['bank_payment_trx'] ?? ''));
            update_post_meta($order_id, 'attach_id', sanitize_text_field($_POST['attach_id'] ?? ''));
        }


        function custom_checkout_field_display_admin_order_meta($order)
        {
            $method = get_post_meta($order->id, '_payment_method', true);
            if ($method != 'sudan_payment_gateway')
                return;

            $attach_id = get_post_meta($order->id, 'attach_id', true);
            $trx_number = get_post_meta($order->id, 'trx_number', true);
            $src = wp_get_attachment_url($attach_id, 'full');
            echo '<p><strong>' . __('mBok Payment Receipt', 'wc-gateway-sudan') . ':</strong> <a class="supg-image-popup" href="javascript:void(0);"><img src="' . $src . '" height="50"/></a></p>';
            echo '<p><strong>' . __('Receipt TRX', 'wc-gateway-sudan') . ':</strong> ' . $trx_number . '</p>';
        }

        function orders_posts_add_columns($columns)
        {
            $new_columns = array();
            foreach ($columns as $column_name => $column_info) {
                $new_columns[$column_name] = $column_info;
                if ('order_total' === $column_name) {
                    $new_columns['trx_number'] = __('Trx', 'wc-gateway-sudan');
                    $new_columns['receipt_image'] = __('Receipt', 'wc-gateway-sudan');
                }
            }
            return $new_columns;
        }
        function orders_posts_column_content($column)
        {
            global $post;
            if ('trx_number' === $column) {
                echo esc_attr(get_post_meta($post->ID, 'trx_number', true));
            }
            if ('receipt_image' === $column) {
                $attach_id = get_post_meta($post->ID, 'attach_id', true);
                $src = wp_get_attachment_url($attach_id, 'full');
                echo '<a class="supg-image-popup" href="javascript:void(0);"><img src="' . esc_attr($src) . '" height="50"/></a>';
            }
        }

        function enqueue_scripts()
        {
            // checkout scripts and styles
            if (is_checkout()) {
                wp_enqueue_script('supg-script', SUPG_PLUGIN_URL . '/assets/js/checkout-payment-script.js' . '?a=' . rand(), array(), '1.1.0', true);
                wp_enqueue_style('supg-style', SUPG_PLUGIN_URL . '/assets/css/checkout-payment-style.css' . '?a=' . rand(), array(), '1.1.0');
                wp_localize_script('supg-script', 'the_ajax_script', array('ajaxurl' => admin_url('admin-ajax.php')));
            }

        }
        
        function admin_scripts()
        {
            global $pagenow, $typenow;

            // admin scripts and styles
            if (($pagenow === 'edit.php' || $pagenow === 'post.php') && $typenow === 'shop_order') {
                wp_enqueue_script('supg-admin-script', SUPG_PLUGIN_URL . '/assets/js/admin-script.js' . '?a=' . rand(), array('jquery'), '1.1.0', true);
                wp_enqueue_style('supg-admin-style', SUPG_PLUGIN_URL . '/assets/css/admin-style.css' . '?a=' . rand(), array(), '1.1.0');
            }
        }

        function add_plugin_settings_link($links, $file) {
            if ($file == plugin_basename(__FILE__)) {
                // This is your own plugin, so don't modify the links
                return $links;
            }
            $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=sudan_payment_gateway')) . '">' . __('Settings') . '</a>';
            array_unshift($links, $settings_link);
            return $links;
        }        
    }
    new SUPG_Plugin;
}