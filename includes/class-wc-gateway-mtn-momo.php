<?php

class WC_Gateway_MTN_MoMo extends WC_Payment_Gateway
{

    private $api;

    private $merchant_key;
    private $secret_key;
    private $payment_item;

    public function __construct()
    {
        $this->id = 'mtn_momo';
        $this->icon = plugins_url('mtn_momo.png', __FILE__);
        $this->has_fields = true;
        $this->method_title = 'MTN Mobile Money';
        $this->method_description = 'Accept payments via MTN Mobile Money through Smobilpay';

        $this->supports = array(
            'products',
        );

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->merchant_key = $this->get_option('merchant_key');
        $this->secret_key = $this->get_option('secret_key');
        $this->payment_item = $this->get_option('payment_item', '20053');

        if ($this->merchant_key && $this->secret_key) {
            $this->api = new WC_Smobilpay_API($this->merchant_key, $this->secret_key);
        }

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'label' => 'Enable MTN Mobile Money',
                'default' => 'no',
            ),
            'title' => array(
                'title' => 'Title',
                'type' => 'text',
                'description' => 'Payment method title shown to customers',
                'default' => 'MTN Mobile Money',
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => 'Description',
                'type' => 'textarea',
                'description' => 'Payment method description shown to customers',
                'default' => 'Pay securely with your MTN Mobile Money account',
            ),
            'merchant_key' => array(
                'title' => 'Merchant Key',
                'type' => 'text',
                'description' => 'Your Smobilpay Public key',
                'desc_tip' => true,
            ),
            'secret_key' => array(
                'title' => 'Secret Key',
                'type' => 'password',
                'description' => 'Your Maviance Smobilpay Secret key',
                'desc_tip' => true,
            ),
            'payment_item' => array(
                'title' => 'Payment Item',
                'type' => 'text',
                'description' => 'Service Code For MTN Momo API Service',
                'default' => '20053',
            ),
        );
    }

    public function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }
?>
        <fieldset>
            <p class="form-row form-row-wide">
                <label for="mtn_phone_number">MTN Phone Number <span class="required">*</span></label>
                <input type="tel" id="mtn_phone_number" name="mtn_phone_number"
                    placeholder="237xxxxxxxxx" pattern="/^237[0-9]{9}$/"
                    maxlength="12" value="237" required />
                <small>Format: 237xxxxxxxxx (Cameroon)</small>
            </p>
        </fieldset>
<?php
    }

    public function validate_fields()
    {
        if (empty($_POST['mtn_phone_number'])) {
            wc_add_notice('Phone number is required', 'error');
            return false;
        }

        $phone = sanitize_text_field($_POST['mtn_phone_number']);
        if (!preg_match('/^237[0-9]{9}$/', $phone)) {
            wc_add_notice('Invalid phone number format. Use: 237xxxxxxxxx', 'error');
            return false;
        }

        return true;
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $phone = sanitize_text_field($_POST['mtn_phone_number']);

        update_post_meta($order_id, '_mtn_phone_number', $phone);

        // Step 1: Get payable item ID
        $payable_result = $this->api->get_payable_item($this->payment_item);

        if (!$payable_result['success']) {
            wc_add_notice('Payment initialization failed. Please try again.', 'error');
            return array('result' => 'failure');
        }

        $payable_item_id = $payable_result['data'][0]['payItemId'] ?? null;

        if (!$payable_item_id) {
            wc_add_notice('Could not retrieve payment item ID', 'error');
            return array('result' => 'failure');
        }

        update_post_meta($order_id, '_smobilpay_payable_item_id', $payable_item_id);

        // Step 2: Initiate transaction
        $quote_data = array(
            'payItemId' => $payable_item_id,
            'amount' => (int) $order->get_total(),
        );

        $quote_result = $this->api->initiate_transaction($quote_data);

        if (!$quote_result['success']) {
            wc_add_notice('Transaction initiation failed: ' . ($quote_result['message'] ?? 'Unknown error'), 'error');
            return array('result' => 'failure');
        }

        $quote_id = $quote_result['data']['quoteId'] ?? null;

        if (!$quote_id) {
            wc_add_notice('Could not retrieve quote ID', 'error');
            return array('result' => 'failure');
        }

        update_post_meta($order_id, '_smobilpay_quote_id', $quote_id);

        // Step 3: Finalize transaction
        $collect_data = array(
            "quoteId" => $quote_id,
            "customerPhonenumber" => $phone,
            "customerEmailaddress" => $order->get_billing_email(),
            "customerName" => $order->get_billing_last_name(),
            "customerAddress" => $order->get_billing_address_1(),
            "serviceNumber" => $phone,
            "trid" => "$order_id"
        );

        $collect_result = $this->api->finalize_transaction($collect_data);

        if (!$collect_result['success']) {
            wc_add_notice('Payment collection failed: ' . ($collect_result['message'] ?? 'Unknown error'), 'error');
            return array('result' => 'failure');
        }

        $trid = $collect_result['data']['trid'] ?? null;
        $status = $collect_result['data']['status'] ?? 'PENDING';

        if ($trid) {
            update_post_meta($order_id, '_smobilpay_trid', $trid);
        }

        if ($status === 'PENDING') {
            $order->update_status('on-hold', 'Awaiting MTN Mobile Money payment confirmation');
            $order->add_order_note(sprintf('Payment initiated. trid: %s. Waiting for customer to confirm on their phone.', $trid));

            WC()->cart->empty_cart();

            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            );
        }

        wc_add_notice('Unexpected payment status: ' . $status, 'error');
        return array('result' => 'failure');
    }

    public function verify_transaction($trid)
    {
        return $this->api->verify_transaction($trid);
    }
}
