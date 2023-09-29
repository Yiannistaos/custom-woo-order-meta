<?php
/**
 * Plugin Name:       Custom WooCommerce Order Meta
 * Plugin URI:        https://www.web357.com
 * Description:       A custom plugin designed to allow users to edit, add, or modify order item meta data within WooCommerce orders. Useful for shops that require extended order meta functionality.
 * Version:           1.0.0
 * Author:            Yiannis Christodoulou
 * Author URI:        https://www.web357.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cw-order-meta
 * Domain Path:       /languages
 */

// Ensure the plugin file is not accessed directly.
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Action to add the admin menu
add_action('admin_menu', 'cwoom_add_admin_menu');

function cwoom_add_admin_menu() {
    add_submenu_page('woocommerce', 'Edit Order Item Meta', 'Edit Order Item Meta', 'manage_woocommerce', 'edit-order-item-meta', 'cwoom_edit_meta_page');
}

function cwoom_admin_notice() {
    if (isset($_GET['cwoom_meta_updated']) && $_GET['cwoom_meta_updated'] == 'true') {
        echo '<div class="notice notice-success is-dismissible"><p>Meta updated successfully.</p></div>';
    }
}
add_action('admin_notices', 'cwoom_admin_notice');

function cwoom_edit_meta_page() {
    global $wpdb;

    echo '<style>
        /* Increase the width of inputs */
        input[type="text"], textarea {
            width: 100%; /* or you can set it to any specific width you want, e.g., 400px */
            box-sizing: border-box; /* This ensures padding and border are included in the total width */
        }
    </style>';

    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : (isset($_GET['order_id']) ? intval($_GET['order_id']) : 0);
    $order_total = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE post_id = %d AND meta_key = '_order_total'", $order_id));
    $updated = false;

    // Handle the update
    if (isset($_POST['update_meta']) && isset($_POST['meta_values']) && is_array($_POST['meta_values'])) {
        foreach ($_POST['meta_values'] as $meta_id => $meta_value) {

            // If the meta_key is _line_tax_data, stripslashes before saving
            if ($_POST['meta_keys'][$meta_id] === '_line_tax_data') {
                $meta_value = stripslashes($meta_value);
            }
            
            $wpdb->update(
                "{$wpdb->prefix}woocommerce_order_itemmeta",
                ['meta_value' => sanitize_text_field($meta_value)],
                ['meta_id' => intval($meta_id)]
            );
        }

        if (isset($_POST['order_total'])) {
            $new_order_total = sanitize_text_field($_POST['order_total']);
            $wpdb->update(
                "{$wpdb->prefix}postmeta",
                ['meta_value' => $new_order_total],
                [
                    'post_id' => $order_id,
                    'meta_key' => '_order_total'
                ]
            );
        }

        $updated = true;

        // Flush cache (using Redis Object Cache)
        wp_cache_flush();

        // Redirect back with a query parameter to indicate success
        wp_redirect(add_query_arg(['cwoom_meta_updated' => 'true', 'order_id' => $order_id], $_SERVER['REQUEST_URI']));
        exit;
    }

    echo '<div class="wrap">';
    echo '<h1>Edit Order Item Meta</h1>';
    echo '<form method="post" action="">';
    echo 'Order ID: <input type="number" name="order_id" value="' . esc_attr($order_id) . '">';
    echo '<input type="submit" value="Load Meta" class="button button-primary">';
    echo '</form>';

    if ($order_id || $updated) {
        // Retrieve order_item_ids for the given order id
        $order_item_ids = $wpdb->get_col($wpdb->prepare("SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id = %d", $order_id));

        if ($order_item_ids) {
            $placeholders = implode(',', array_fill(0, count($order_item_ids), '%d'));
            // Retrieve meta data for the given order item ids
            $results = $wpdb->get_results($wpdb->prepare("SELECT meta_id, order_item_id, meta_key, meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id IN ($placeholders)", $order_item_ids));

            if ($results) {
                echo '<form method="post" action="">';
                echo '<input type="hidden" name="order_id" value="' . esc_attr($order_id) . '">';

                foreach ($results as $meta) {
                    echo '<p>';
                    echo '<label for="meta_' . esc_attr($meta->meta_id) . '">' . esc_html($meta->meta_key) . ' (Order Item: ' . esc_html($meta->order_item_id) . '): ';
                    echo $meta->meta_key === 'tax_amount' ? '<span style="color:red;">(Invoice\'s Tax Amount)</span>' : '';
                    echo '</label> ';

                    // Check if the meta_key is _line_tax_data
                    if ($meta->meta_key === '_line_tax_data') {
                        echo '<textarea id="meta_' . esc_attr($meta->meta_id) . '" name="meta_values[' . esc_attr($meta->meta_id) . ']" rows="5" cols="50">' . stripslashes($meta->meta_value) . '</textarea>';
                    } else {
                        
                        echo '<input type="text" id="meta_' . esc_attr($meta->meta_id) . '" name="meta_values[' . esc_attr($meta->meta_id) . ']" value="' . esc_attr($meta->meta_value) . '">';
                    }

                    echo '</p>';
                }

                // order total
                echo '<p>';
                echo '<label for="order_total">Order Total:</label> <span style="color:red;">(Invoice\'s Tax Amount)</span> ';
                echo '<input type="text" id="order_total" name="order_total" value="' . esc_attr($order_total) . '">';
                echo '</p>';

                echo '<input type="submit" name="update_meta" value="Update Meta" class="button button-primary">';
                echo '</form>';
            } else {
                echo '<p>No meta found for this order ID.</p>';
            }
        } else {
            echo '<p>No order items found for this order ID.</p>';
        }
    }

    echo '</div>';  // closing wrap div
}
