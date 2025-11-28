<?php
/**
 * Plugin Name: WooCommerce Smobilpay Gateway
 * Plugin URI: https://ange-arsene.is-a.dev
 * Description: Accept MTN Mobile Money and Orange Money payments via Smobilpay S3P API
 * Version: 1.0.0
 * Author: Ange Arsene
 * Author URI: https://ange-arsene.is-a.dev
 * Text Domain: wc-smobilpay
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.4
 * WC requires at least: 6.0
 * WC tested up to: 8.5
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

define('WC_SMOBILPAY_VERSION', '1.0.0');
define('WC_SMOBILPAY_PLUGIN_FILE', __FILE__);
define('WC_SMOBILPAY_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Initialize the gateway
add_action('plugins_loaded', 'wc_smobilpay_init', 11);

function wc_smobilpay_init() {
    
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    // Include gateway classes
    require_once WC_SMOBILPAY_PLUGIN_PATH . 'includes/class-wc-smobilpay-api.php';
    require_once WC_SMOBILPAY_PLUGIN_PATH . 'includes/class-wc-gateway-mtn-momo.php';
    require_once WC_SMOBILPAY_PLUGIN_PATH . 'includes/class-wc-gateway-orange-money.php';

    // Add the gateways to WooCommerce
    add_filter('woocommerce_payment_gateways', 'wc_smobilpay_add_gateways');
}

function wc_smobilpay_add_gateways($gateways) {
    $gateways[] = 'WC_Gateway_MTN_MoMo';
    $gateways[] = 'WC_Gateway_Orange_Money';
    return $gateways;
}

// Handle webhook callbacks
add_action('woocommerce_api_wc_smobilpay_webhook', 'wc_smobilpay_handle_webhook');

function wc_smobilpay_handle_webhook() {
    $raw_post = file_get_contents('php://input');
    $data = json_decode($raw_post, true);
    
    if (!$data || !isset($data['merchantTransId'])) {
        wp_die('Invalid webhook data', 'Webhook Error', array('response' => 400));
    }

    $order_id = intval($data['merchantTransId']);
    $order = wc_get_order($order_id);

    if (!$order) {
        wp_die('Order not found', 'Webhook Error', array('response' => 404));
    }

    // Log the webhook
    $order->add_order_note(sprintf('Webhook received: %s', wp_json_encode($data)));

    // Check transaction status
    $payment_method = $order->get_payment_method();
    $gateway = WC()->payment_gateways->payment_gateways()[$payment_method] ?? null;

    if ($gateway && method_exists($gateway, 'verify_transaction')) {
        $ptn = get_post_meta($order_id, '_smobilpay_ptn', true);
        
        if ($ptn) {
            $result = $gateway->verify_transaction($ptn);
            
            if ($result['success'] && $result['status'] === 'SUCCESS') {
                $order->payment_complete($ptn);
                $order->add_order_note(sprintf('Payment verified via webhook. PTN: %s', $ptn));
            }
        }
    }

    status_header(200);
    die('OK');
}