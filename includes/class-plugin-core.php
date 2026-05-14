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

            // Admin orders table in woocommerce
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

        function plugin_links($links)
        {
            $plugin_links = array(
                '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=sudan_payment_gateway')) . '">' . esc_html__('Configure', 'wc-sudan-gateway') . '</a>'
            );

            return array_merge($plugin_links, $links);
        }

        function save_receipt_image()
        {
            check_ajax_referer('invoice_response_nonce', 'nonce');

            if (!isset($_FILES['file'])) {
                wp_send_json_error('File not found.');
            }

            $file = $_FILES['file'];
            $max_size = 2 * 1024 * 1024; // 2MB

            if (!empty($file['size']) && $file['size'] > $max_size) {
                wp_send_json_error('The file size must not exceed 2MB.');
            }

            if (!empty($file['error'])) {
                wp_send_json_error('Upload error.');
            }

            $filetype = wp_check_filetype_and_ext(
                $file['tmp_name'],
                $file['name'],
                array(
                    'jpg|jpeg' => 'image/jpeg',
                    'png'      => 'image/png',
                    'gif'      => 'image/gif',
                )
            );

            if (empty($filetype['type']) || empty($filetype['ext'])) {
                wp_send_json_error('Invalid file type.');
            }

            $image_info = getimagesize($file['tmp_name']);

            if ($image_info === false) {
                wp_send_json_error('File is not a valid image.');
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';

            $uploaded = wp_handle_upload($file, array(
                'test_form' => false,
                'mimes' => array(
                    'jpg|jpeg' => 'image/jpeg',
                    'png'      => 'image/png',
                    'gif'      => 'image/gif',
                ),
            ));

            if (isset($uploaded['error'])) {
                wp_send_json_error($uploaded['error']);
            }

            $file_path = $uploaded['file'];
            $file_url  = $uploaded['url'];
            $mime_type = $uploaded['type'];
            $file_name = basename($file_path);

            $attachment = array(
                'guid'           => esc_url_raw($file_url),
                'post_mime_type' => $mime_type,
                'post_title'     => sanitize_file_name(pathinfo($file_name, PATHINFO_FILENAME)),
                'post_content'   => '',
                'post_status'    => 'inherit',
            );

            $attach_id = wp_insert_attachment($attachment, $file_path);

            if (is_wp_error($attach_id)) {
                wp_send_json_error('Could not save attachment.');
            }

            require_once ABSPATH . 'wp-admin/includes/image.php';

            $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
            wp_update_attachment_metadata($attach_id, $attach_data);

            wp_send_json_success(array(
                'attach_id' => absint($attach_id),
            ));
        }

        function update_order_meta($order_id)
        {
            if (empty($_POST['payment_method']) || $_POST['payment_method'] !== 'sudan_payment_gateway') {
                return;
            }

            update_post_meta($order_id, 'trx_number', sanitize_text_field($_POST['bank_payment_trx'] ?? ''));
            update_post_meta($order_id, 'attach_id', absint($_POST['attach_id'] ?? 0));
        }

        function custom_checkout_field_display_admin_order_meta($order)
        {
            $order_id = method_exists($order, 'get_id') ? $order->get_id() : $order->id;

            $method = get_post_meta($order_id, '_payment_method', true);

            if ($method !== 'sudan_payment_gateway') {
                return;
            }

            $attach_id = absint(get_post_meta($order_id, 'attach_id', true));
            $trx_number = get_post_meta($order_id, 'trx_number', true);
            $src = wp_get_attachment_url($attach_id);

            if ($src) {
                echo '<p><strong>' . esc_html__('Payment Receipt', 'wc-sudan-gateway') . ':</strong> <a class="supg-image-popup" href="javascript:void(0);"><img src="' . esc_url($src) . '" height="50" alt="" /></a></p>';
            }

            echo '<p><strong>' . esc_html__('Receipt TRX', 'wc-sudan-gateway') . ':</strong> ' . esc_html($trx_number) . '</p>';
        }

        function orders_posts_add_columns($columns)
        {
            $new_columns = array();

            foreach ($columns as $column_name => $column_info) {
                $new_columns[$column_name] = $column_info;

                if ('order_total' === $column_name) {
                    $new_columns['trx_number'] = esc_html__('Trx', 'wc-sudan-gateway');
                    $new_columns['receipt_image'] = esc_html__('Receipt', 'wc-sudan-gateway');
                }
            }

            return $new_columns;
        }

        function orders_posts_column_content($column)
        {
            global $post;

            if ('trx_number' === $column) {
                echo esc_html(get_post_meta($post->ID, 'trx_number', true));
            }

            if ('receipt_image' === $column) {
                $attach_id = absint(get_post_meta($post->ID, 'attach_id', true));
                $src = wp_get_attachment_url($attach_id);

                if ($src) {
                    echo '<a class="supg-image-popup" href="javascript:void(0);"><img src="' . esc_url($src) . '" height="50" alt="" /></a>';
                }
            }
        }

        function enqueue_scripts()
        {
            if (is_checkout()) {
                wp_enqueue_script('supg-script', SUPG_PLUGIN_URL . '/assets/js/checkout-payment-script.js', array('jquery'), '1.1.1', true);
                wp_enqueue_style('supg-style', SUPG_PLUGIN_URL . '/assets/css/checkout-payment-style.css', array(), '1.1.1');

                wp_localize_script('supg-script', 'the_ajax_script', array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce'   => wp_create_nonce('invoice_response_nonce'),
                ));
            }
        }

        function admin_scripts()
        {
            global $pagenow, $typenow;

            if (($pagenow === 'edit.php' || $pagenow === 'post.php') && $typenow === 'shop_order') {
                wp_enqueue_script('supg-admin-script', SUPG_PLUGIN_URL . '/assets/js/admin-script.js', array('jquery'), '1.1.1', true);
                wp_enqueue_style('supg-admin-style', SUPG_PLUGIN_URL . '/assets/css/admin-style.css', array(), '1.1.1');
            }
        }

        function add_plugin_settings_link($links, $file)
        {
            if ($file == plugin_basename(__FILE__)) {
                return $links;
            }

            $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=sudan_payment_gateway')) . '">' . esc_html__('Settings', 'wc-sudan-gateway') . '</a>';
            array_unshift($links, $settings_link);

            return $links;
        }
    }

    new SUPG_Plugin;
}