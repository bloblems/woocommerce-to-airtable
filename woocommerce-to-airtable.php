<?php
/**
 * Plugin Name: WooCommerce To Airtable
 * Description: WooCommerce To Airtable plugin synchronizes WooCommerce order processing to Airtable. It sends new processing orders and their updates to Airtable, thus maintaining a seamless order tracking system.
 * Version: 1.0
 * Author: Byron Jacobs
 * Author URI: https://byronjacobs.co.za
 * License: GPLv2 or later
 * Text Domain: woocommerce-to-airtable
 */

add_action('woocommerce_order_status_processing', 'send_order_to_airtable', 10, 1);
add_action('woocommerce_order_status_changed', 'send_order_to_airtable', 10, 1);

function send_order_to_airtable($order_id) {

    $order = wc_get_order($order_id);
    $order_data = $order->get_data();

    $token = '';
    $baseId = '';
    $tableName = '';

    $items = $order->get_items();
    $products = array();
    $order_quantity = array();
    foreach($items as $item){
        $products[] = $item->get_name();
        $order_quantity[] = $item->get_name() . '(' . $item->get_quantity() . ')';
    }

    $fields = [
        'Order #' => (string)$order_id,
        'Date' => $order->get_date_created()->date('Y-m-d H:i:s'),
        'Order Total' => $order->get_total(),
        'Customer' => $order_data['billing']['first_name'] . ' ' . $order_data['billing']['last_name'],
        'Status' => $order->get_status(),
        'Product' => implode(', ', $products),
        'Order Quantity' => implode(', ', $order_quantity),
        'Customer Contact Number' => $order_data['billing']['phone'],
        'Customer Email' => $order_data['billing']['email'],
        'Shipping Address' => $order_data['shipping']['address_1'] . ', ' . $order_data['shipping']['city'] . ', ' . $order_data['shipping']['postcode'] . ', ' . $order_data['shipping']['country'],
        'Billing Address' => $order_data['billing']['address_1'] . ', ' . $order_data['billing']['city'] . ', ' . $order_data['billing']['postcode'] . ', ' . $order_data['billing']['country'],
        'Currency' => $order->get_currency(),
        'Shipping Price' => $order->get_shipping_total(),
        'Notes' => $order->get_customer_note(),
    ];

    $json = json_encode(['fields' => $fields]);

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
function airtable_order_metabox() {
    add_meta_box('airtable_order_metabox', 'Airtable Status', 'airtable_order_metabox_callback', 'shop_order', 'side', 'default');
}

// Meta Box Callback
function airtable_order_metabox_callback($post) {
    global $post, $woocommerce, $theorder;

    if ( ! is_object( $theorder ) ) {
        $theorder = wc_get_order( $post->ID );
    }

    $notes = wc_get_order_notes( array( 'order_id' => $theorder->get_id() ) );
    foreach( $notes as $note ) {
        if (strpos($note->content, 'Airtable') !== false) {
            echo '<strong>'.$note->content.'</strong>';
        }
    }
}
