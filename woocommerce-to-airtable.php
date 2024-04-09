<?php

/**
 * Plugin Name: WooCommerce To Airtable
 * Description: WooCommerce To Airtable plugin synchronizes WooCommerce order processing to Airtable. It sends new processing orders and their updates to Airtable, thus maintaining a seamless order tracking system.
 * Version: 1.1
 * Author: Byron Jacobs
 * Author URI: https://byronjacobs.co.za
 * License: GPLv2 or later
 * Text Domain: woocommerce-to-airtable
 */

add_action('woocommerce_order_status_processing', 'send_order_to_airtable', 10, 1);
add_action('woocommerce_order_status_changed', 'send_order_to_airtable', 10, 1);

function send_order_to_airtable($order_id)
{

    $order = wc_get_order($order_id);
    $order_data = $order->get_data();

    $token = '';
    $baseId = '';
   
	//table names
	$defaultTableName = ''; 
   	$tableNameForSpecialSKU = ''; 
	
    $specialSKUs = ['', '']; // Add your special SKUs here
    $containsSpecialSKU = false; 

    $items = $order->get_items();
    $products = array();
    $order_quantity = array();
    foreach ($items as $item) {
        $product = $item->get_product();
        $sku = $product->get_sku();  // Get SKU of the product
        if (in_array($sku, $specialSKUs)) {
            $containsSpecialSKU = true;
        }
        $products[] = $sku;
        $order_quantity[] = $sku . ' (' . $item->get_quantity() . ')';
    }

    $tableName = $containsSpecialSKU ? $tableNameForSpecialSKU : $defaultTableName;
	
    // Capitalize first name and last name
    $first_name = ucfirst($order_data['billing']['first_name']);
    $last_name = ucfirst($order_data['billing']['last_name']);

    $fields = [
        'Order #' => (string)$order_id,
        'Date' => $order->get_date_created()->date('Y-m-d H:i:s'),
        'Order Total' => floatval($order->get_total()),
        'Customer' => $first_name . ' ' . $last_name,
        'Status' => $order->get_status(),
        'Product' => implode(', ', $products),
        'Order Quantity' => implode(', ', $order_quantity),
        'Customer Phone Number' => $order_data['billing']['phone'],
        'Customer Email' => $order_data['billing']['email'],
        'Shipping Address' => $order_data['shipping']['address_1'] . ', ' . $order_data['shipping']['city'] . ', ' . $order_data['shipping']['postcode'] . ', ' . $order_data['shipping']['country'],
		'State' => $order_data['shipping']['state'],
        'Billing Address' => $order_data['billing']['address_1'] . ', ' . $order_data['billing']['city'] . ', ' . $order_data['billing']['postcode'] . ', ' . $order_data['billing']['country'],
        'Currency' => $order->get_currency(),
        'Shipping Price' => floatval($order->get_shipping_total()),
        'Customer Notes' => $order->get_customer_note(),
    ];

    $json = json_encode(['fields' => $fields], JSON_UNESCAPED_UNICODE);


    // Save payload in a custom field on the order
    $order->add_order_note('Payload sent to Airtable: ' . $json);

    $ch = curl_init();
    $airtable_record_id = get_post_meta($order_id, 'airtable_record_id', true);
    if ($airtable_record_id) {
        // Update existing record
        $url = "https://api.airtable.com/v0/{$baseId}/{$tableName}/{$airtable_record_id}";
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    } else {
        // Create new record
        $url = "https://api.airtable.com/v0/{$baseId}/{$tableName}";
        curl_setopt($ch, CURLOPT_POST, true);
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

    $response = curl_exec($ch);

    if ($errno = curl_errno($ch)) {
        $error_message = curl_strerror($errno);
        $order->add_order_note("Failed to send order to Airtable. cURL error ({$errno}):\n {$error_message}");
    } else {
        $response_decoded = json_decode($response, true);
        if (isset($response_decoded['error'])) {
            $order->add_order_note('Failed to send order to Airtable. Airtable API error: ' . $response_decoded['error']['message']);
        } else {
            if (!$airtable_record_id) {
                // If this is a new record, save the Airtable record ID as post meta data
                update_post_meta($order_id, 'airtable_record_id', $response_decoded['id']);
            }
            $order->add_order_note("Order sent to Airtable successfully");
        }
    }
}

// Add Meta Box for WooCommerce Order
add_action('add_meta_boxes', 'airtable_order_metabox');
function airtable_order_metabox()
{
    add_meta_box('airtable_order_metabox', 'Airtable Status', 'airtable_order_metabox_callback', 'shop_order', 'side', 'default');
}

// Meta Box Callback
function airtable_order_metabox_callback($post)
{
    global $post, $woocommerce, $theorder;

    if (!is_object($theorder)) {
        $theorder = wc_get_order($post->ID);
    }

    $notes = wc_get_order_notes(array('order_id' => $theorder->get_id()));
    foreach ($notes as $note) {
        if (strpos($note->content, 'Airtable') !== false) {
            echo '<strong>' . $note->content . '</strong>';
        }
    }
}

// The following function adds a new action button named 'Resend to Airtable' on the order page
add_action('woocommerce_order_actions', 'add_resend_order_to_airtable_action');
function add_resend_order_to_airtable_action($actions)
{
    $actions['resend_to_airtable'] = __('Resend to Airtable', 'woocommerce');
    return $actions;
}

// When the 'Resend to Airtable' button is clicked, this function will be triggered, which then calls your send_order_to_airtable() function
add_action('woocommerce_order_action_resend_to_airtable', 'process_resend_order_to_airtable_action');
function process_resend_order_to_airtable_action($order)
{
    send_order_to_airtable($order->get_id());
}
