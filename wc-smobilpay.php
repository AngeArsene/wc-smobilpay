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

function wc_smobilpay_init()
{

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

function wc_smobilpay_add_gateways($gateways)
{
    $gateways[] = 'WC_Gateway_MTN_MoMo';
    $gateways[] = 'WC_Gateway_Orange_Money';
    return $gateways;
}

function wc_smobilpay_activate($payload)
{
    $secret = '';

    // 1. Get signature WooCommerce sent
    $signature = $_SERVER['HTTP_X_WC_WEBHOOK_SIGNATURE'] ?? '';

    if (isset($_SERVER['HTTP_X_WC_WEBHOOK_SIGNATURE'])) {
        $signature = $_SERVER['HTTP_X_WC_WEBHOOK_SIGNATURE'];
        $calculated = base64_encode(hash_hmac('sha256', $payload, $secret, true));

    } else if (isset($_SERVER['HTTP_X_SIGNATURE'])) {
        $signature = $_SERVER['HTTP_X_SIGNATURE'];
        $calculated = hash_hmac('sha1', $payload, $secret);
    }

    // 4. Compare signatures
    if (!hash_equals($calculated, $signature)) {
        http_response_code(401);
        die('Invalid signature');
    }
}

// Handle webhook callbacks
add_action('woocommerce_api_wc_smobilpay_webhook', 'wc_smobilpay_handle_webhook');

function wc_smobilpay_handle_webhook()
{
    $raw_post = file_get_contents('php://input');
    wc_smobilpay_activate($raw_post);

    $data = json_decode($raw_post, true);

    if (!$data || (!isset($data['trid']) && !isset($data['id']))) {
        wp_die('Invalid webhook data', 'Webhook Error', array('response' => 400));
    }


    $order_id = intval($data['trid'] ?? $data['id']);
    $order = wc_get_order($order_id);

    if (!$order) {
        wp_die('Order not found', 'Webhook Error', array('response' => 404));
    }

    // Log the webhook
    $order->add_order_note(sprintf('Webhook received: %s', wp_json_encode($data)));

    // Check transaction status
    $payment_method = $order->get_payment_method();
    $gateway = WC()->payment_gateways->payment_gateways()[$payment_method] ?? null;

    $errorMessages = [
        "mtn_momo" => [

            // SUCCESS
            "0" => [
                "en" => "The payment was successful.",
                "fr" => "Le paiement a été effectué avec succès.",
            ],

            // ORIGINAL ENTRIES
            "703202" => [
                "en" => "You have rejected the transaction.",
                "fr" => "Vous avez rejeté la transaction.",
            ],
            "703201" => [
                "en" => "You did not confirm the transaction.",
                "fr" => "Vous n'avez pas confirmé la transaction.",
            ],
            "704005" => [
                "en" => "The payment failed.",
                "fr" => "Le paiement a échoué.",
            ],

            // NEW ENTRIES (REQUESTED)

            "42001" => [
                "en" => "Service number or bill number not found.",
                "fr" => "Numéro de service ou numéro de facture introuvable.",
            ],

            "703112" => [
                "en" => "Recipient account limit (daily/weekly/monthly) has been reached.",
                "fr" => "La limite du compte du destinataire (journalière/hebdomadaire/mensuelle) a été atteinte.",
            ],

            "703111" => [
                "en" => "Your account limit (daily/weekly/monthly) has been reached.",
                "fr" => "La limite de votre compte (journalière/hebdomadaire/mensuelle) a été atteinte.",
            ],

            "703203" => [
                "en" => "Invalid PIN or confirmation token.",
                "fr" => "Code PIN ou jeton de confirmation invalide.",
            ],

            "703108" => [
                "en" => "Insufficient balance.",
                "fr" => "Solde insuffisant.",
            ],

            "703117" => [
                "en" => "Your account is not enabled for this service.",
                "fr" => "Votre compte n'est pas activé pour ce service.",
            ],

            "702103" => [
                "en" => "The amount is above the allowed limit.",
                "fr" => "Le montant dépasse la limite autorisée.",
            ],
        ],

        "orange_money" => [

            // SUCCESS
            "0" => [
                "en" => "The payment was successful.",
                "fr" => "Le paiement a été effectué avec succès.",
            ],

            // ORIGINAL ENTRIES
            "703202" => [
                "en" => "You have rejected the transaction.",
                "fr" => "Vous avez rejeté la transaction.",
            ],
            "703108" => [
                "en" => "You have insufficient balance.",
                "fr" => "Vous n'avez pas un solde suffisant.",
            ],
            "703201" => [
                "en" => "The customer did not confirm the transaction.",
                "fr" => "Vous n'avez pas confirmé la transaction.",
            ],
            "703000" => [
                "en" => "The payment failed.",
                "fr" => "Le paiement a échoué.",
            ],

            // NEW ENTRIES (REQUESTED)

            "42001" => [
                "en" => "Service number or bill number not found.",
                "fr" => "Numéro de service ou numéro de facture introuvable.",
            ],

            "703112" => [
                "en" => "Recipient account limit (daily/weekly/monthly) has been reached.",
                "fr" => "La limite du compte du destinataire (journalière/hebdomadaire/mensuelle) a été atteinte.",
            ],

            "703111" => [
                "en" => "Your account limit (daily/weekly/monthly) has been reached.",
                "fr" => "La limite de votre compte (journalière/hebdomadaire/mensuelle) a été atteinte.",
            ],

            "703203" => [
                "en" => "Invalid PIN or confirmation token.",
                "fr" => "Code PIN ou jeton de confirmation invalide.",
            ],

            "703117" => [
                "en" => "Your account is not enabled for this service.",
                "fr" => "Votre compte n'est pas activé pour ce service.",
            ],

            "702103" => [
                "en" => "The amount is above the allowed limit.",
                "fr" => "Le montant dépasse la limite autorisée.",
            ],
        ],
    ];


    // Include utility functions files
    require_once WC_SMOBILPAY_PLUGIN_PATH . 'utils/templates.php';
    require_once WC_SMOBILPAY_PLUGIN_PATH . 'utils/utilities.php';

    if ($gateway && method_exists($gateway, 'verify_transaction')) {
        $trid = $order_id;

        if ($trid) {
            $result = $gateway->verify_transaction($trid);

            if (!empty($result)) {
                file_put_contents(__DIR__ . '/data.json', wp_json_encode($result));
            }

            if ($result['success'] && $result['data'][0]['status'] === 'SUCCESS') {
                $order->payment_complete($trid);
                $order->add_order_note(sprintf('Payment verified via webhook. trid: %s', $trid));

                email_notify_client($order, $errorMessages[$payment_method]["0"]);
                whatsapp_notify_client($order, $errorMessages[$payment_method]["0"]);
            } else if ($result['success'] && $result['data'][0]['status'] === 'ERRORED') {
                $order->update_status('failed', $errorMessages[$payment_method][$result['data'][0]['errorCode'] ?? "703000"]['en']);

                email_notify_client($order, $errorMessages[$payment_method][$result['data'][0]['errorCode'] ?? "703000"]);
                whatsapp_notify_client($order, $errorMessages[$payment_method][$result['data'][0]['errorCode'] ?? "703000"]);
            }
        }
    }

    status_header(200);
    die('OK');
}

function whatsapp_notify_client(bool|\WC_Order|\WC_Order_Refund $order, array $errorMessages)
{
    $phone_number = get_phone_number(['phone_number' => $order->get_billing_phone()]);

    $variables = [
        'id' => $order->get_id(),
        'first_name' => $order->get_billing_first_name(),
        'last_name' => $order->get_billing_last_name(),
        'email' => $order->get_billing_email(),
        'phone_number' => $phone_number,
        'city' => $order->get_billing_city(),
        'neighborhood' => $order->get_billing_address_1(),
        'product_names' => format_order_items($order),
        'payment_method' => wc_get_payment_gateway_by_order($order)->get_title(),
        'shipping_total' => number_format($order->get_total(), 0, '', ''),
        'total' => number_format($order->get_shipping_total(), 0, '', ''),
        'fr' => $errorMessages['fr'],
        'en' => $errorMessages['en'],
    ];

    $message = render('order_notification_template', $variables);

    $payment_method = $order->get_payment_method();
    $gateway = WC()->payment_gateways->payment_gateways()[$payment_method] ?? null;

    send_whatsapp_message($phone_number, $message, $gateway->whatsapp_api_key);
}

function email_notify_client(\WC_Order $order, array $errorMessages)
{
    $to = $order->get_billing_email();

    $subject = sprintf("Order #%s – Payment Update | Mise à jour du paiement – Commande n° %s ", $order->get_id(), $order->get_id());

    $variables = [
        'logo_url' => wc_smobilpay_get_site_logo(),
        'id' => $order->get_id(),
        'first_name' => $order->get_billing_first_name(),
        'last_name' => $order->get_billing_last_name(),
        'email' => $order->get_billing_email(),
        'phone_number' => get_phone_number(['phone_number' => $order->get_billing_phone()]),
        'city' => $order->get_billing_city(),
        'neighborhood' => $order->get_billing_address_1(),
        'product_names' => format_order_items($order),
        'payment_method' => wc_get_payment_gateway_by_order($order)->get_title(),
        'shipping_total' => number_format($order->get_total(), 0, '', ''),
        'total' => number_format($order->get_shipping_total(), 0, '', ''),
        'fr' => $errorMessages['fr'],
        'en' => $errorMessages['en'],
    ];

    $message  = render('order_notification_email_template', $variables);

    $headers = ['Content-Type: text/plain; charset=UTF-8'];

    wc_mail($to, $subject, $message, $headers);
}


function wc_smobilpay_get_site_logo()
{
    $custom_logo_id = get_theme_mod('custom_logo');

    if ($custom_logo_id) {
        return wp_get_attachment_image_url($custom_logo_id, 'full');
    }

    // fallback to site icon if available
    $site_icon = get_site_icon_url();
    if ($site_icon) {
        return $site_icon;
    }

    // fallback to home URL if nothing else exists
    return home_url();
}
