<?php

function mbok_add_gateways($gateways)
{
    $gateways[] = 'MBOK_Payment_Gateway';
    return $gateways;
}
add_filter('woocommerce_payment_gateways', 'mbok_add_gateways');


/**
 * Adds plugin page links
 */
function mbok_gateway_plugin_links($links)
{

    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=mbok_gateway') . '">' . __('Configure', 'wc-gateway-offline') . '</a>'
    );

    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'mbok_gateway_plugin_links');


function mbok_ajax_load_scripts()
{
    // load our jquery file that sends the $.post request
    wp_enqueue_script("common-ajax", plugin_dir_url(__FILE__) . '/js/common.js', array('jquery'));

    // make the ajaxurl var available to the above script
    wp_localize_script('common-ajax', 'the_ajax_script', array('ajaxurl' => admin_url('admin-ajax.php')));
}
add_action('wp_print_scripts', 'mbok_ajax_load_scripts');

function mbok_save_receipt_image() {
    $upload_dir = wp_upload_dir(); // Get the WordPress upload directory
    $mbok_dir = $upload_dir['basedir'] . '/mbok'; // Set the directory to save the image
    if (!file_exists($mbok_dir)) { // Check if the directory exists
        mkdir($mbok_dir, 0755, true); // Create the directory if it doesn't exist
    }

    if(!isset($_FILES['file'])){
        echo 'Error: $_FILES[file] not set';
        exit;
    }

    $file = $_FILES['file']; // Get the uploaded file
    $order_id = rand(1,200000);//$_POST['order_id']; // Get the order ID

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


    // Set the file name and move the file to the mbok directory
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = $order_id . '.' . $file_extension;
    $file_path = $mbok_dir . '/' . $file_name;
    move_uploaded_file($file['tmp_name'], $file_path);

    // Add the file to the WordPress media library
    $attachment = array(
        'guid' => $upload_dir['url'] . '/mbok/' . $file_name,
        'post_mime_type' => $mime_type,
        'post_title' => $file_name,
        'post_content' => '',
        'post_status' => 'inherit'
    );
    $attach_id = wp_insert_attachment($attachment, $file_path);
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
    wp_update_attachment_metadata($attach_id, $attach_data);

    echo $attach_id;
    exit;
}

add_action('wp_ajax_invoice_response', 'mbok_save_receipt_image');
add_action('wp_ajax_nopriv_invoice_response', 'mbok_save_receipt_image');

