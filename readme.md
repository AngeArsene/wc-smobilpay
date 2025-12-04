# WooCommerce Smobilpay Gateway

Accept MTN Mobile Money and Orange Money payments on your WooCommerce store via the Smobilpay S3P API.

## Description

This WordPress plugin integrates Smobilpay's payment gateway with WooCommerce, enabling customers in Cameroon to pay for their orders using:

- **MTN Mobile Money (MoMo)**
- **Orange Money**

The plugin provides seamless payment processing with automatic webhook notifications and comprehensive transaction verification.

## Features

- ✅ Support for MTN Mobile Money and Orange Money
- ✅ Secure webhook verification with HMAC signatures
- ✅ Automatic transaction status updates
- ✅ Email and WhatsApp notifications for payment status
- ✅ Bilingual error messages (English/French)
- ✅ Comprehensive error handling
- ✅ WooCommerce order notes integration
- ✅ Easy configuration through WooCommerce settings

## Requirements

- WordPress 5.8 or higher
- WooCommerce 6.0 or higher
- PHP 7.2.5 or higher
- Composer (for dependency management)
- Valid Smobilpay merchant account

## Installation

### Via Composer (Recommended)

1. Clone or download this repository to your WordPress plugins directory:

```bash
cd wp-content/plugins
git clone https://github.com/all-ready237/wc-smobilpay.git
```

2. Install dependencies:

```bash
cd wc-smobilpay
composer install
```

3. Activate the plugin through the WordPress admin panel:
   - Go to **Plugins** → **Installed Plugins**
   - Find "WooCommerce Smobilpay Gateway"
   - Click **Activate**

### Manual Installation

1. Download the plugin files
2. Upload the `wc-smobilpay` folder to `/wp-content/plugins/`
3. Run `composer install` in the plugin directory
4. Activate the plugin through WordPress admin

## Configuration

### 1. MTN Mobile Money Setup

1. Navigate to **WooCommerce** → **Settings** → **Payments**
2. Click on **MTN Mobile Money**
3. Configure the following settings:
   - **Enable/Disable**: Check to enable MTN MoMo payments
   - **Title**: Payment method title (default: "MTN Mobile Money")
   - **Description**: Customer-facing description
   - **Merchant Key**: Your Smobilpay merchant key for MTN
   - **WhatsApp API Key**: (Optional) For sending WhatsApp notifications
4. Save changes

### 2. Orange Money Setup

1. Navigate to **WooCommerce** → **Settings** → **Payments**
2. Click on **Orange Money**
3. Configure the following settings:
   - **Enable/Disable**: Check to enable Orange Money payments
   - **Title**: Payment method title (default: "Orange Money")
   - **Description**: Customer-facing description
   - **Merchant Key**: Your Smobilpay merchant key for Orange Money
   - **WhatsApp API Key**: (Optional) For sending WhatsApp notifications
4. Save changes

### 3. Webhook Configuration

The plugin automatically handles webhooks at:

```
https://yoursite.com/wc-api/wc_smobilpay_webhook
```

**Important:** Update the webhook secret in `wc-smobilpay.php`:

```php
$secret = 'your-actual-webhook-secret-here';
```

Register this webhook URL in your Smobilpay merchant dashboard.

## Usage

Once configured, customers will see the enabled payment methods at checkout:

1. Customer selects MTN Mobile Money or Orange Money
2. Customer enters their phone number
3. Customer receives a payment prompt on their mobile device
4. Customer confirms the payment
5. Plugin receives webhook notification
6. Order status is automatically updated
7. Customer receives email and WhatsApp confirmation (if configured)

## Error Codes

The plugin handles various transaction error codes:

### Common Errors (Both Providers)

| Code   | English                                       | French                                                |
| ------ | --------------------------------------------- | ----------------------------------------------------- |
| 0      | The payment was successful.                   | Le paiement a été effectué avec succès.           |
| 42001  | Service number or bill number not found.      | Numéro de service ou numéro de facture introuvable. |
| 703108 | Insufficient balance.                         | Solde insuffisant.                                    |
| 703111 | Your account limit has been reached.          | La limite de votre compte a été atteinte.           |
| 703112 | Recipient account limit has been reached.     | La limite du compte du destinataire a été atteinte. |
| 703201 | You did not confirm the transaction.          | Vous n'avez pas confirmé la transaction.             |
| 703202 | You have rejected the transaction.            | Vous avez rejeté la transaction.                     |
| 703203 | Invalid PIN or confirmation token.            | Code PIN ou jeton de confirmation invalide.           |
| 703117 | Your account is not enabled for this service. | Votre compte n'est pas activé pour ce service.       |
| 702103 | The amount is above the allowed limit.        | Le montant dépasse la limite autorisée.             |

### MTN-Specific Errors

- **704005**: The payment failed.

### Orange-Specific Errors

- **703000**: The payment failed.

## File Structure

```
wc-smobilpay/
├── includes/
│   ├── class-wc-smobilpay-api.php          # API integration class
│   ├── class-wc-gateway-mtn-momo.php       # MTN MoMo gateway
│   └── class-wc-gateway-orange-money.php   # Orange Money gateway
├── utils/
│   ├── templates.php                        # Template rendering functions
│   └── utilities.php                        # Helper functions
├── vendor/                                   # Composer dependencies
├── composer.json                            # Composer configuration
├── wc-smobilpay.php                         # Main plugin file
└── README.md                                # This file
```

## Dependencies

- **guzzlehttp/guzzle** (^7.10): HTTP client for API requests

## Hooks and Filters

### Actions

- `woocommerce_api_wc_smobilpay_webhook`: Webhook callback handler

### Filters

- `woocommerce_payment_gateways`: Adds payment gateways to WooCommerce

## Development

### Testing Webhooks Locally

Use ngrok or similar tools to expose your local environment:

```bash
ngrok http 80
```

Update your Smobilpay webhook URL with the ngrok URL.

### Debugging

Enable WooCommerce logging:

1. Go to **WooCommerce** → **Status** → **Logs**
2. Check logs for `wc-smobilpay-*` entries

## Support

For support, please contact:

- **Author**: Ange Arsene
- **Email**: nkenmandenga@gmail.com
- **Website**: https://ange-arsene.is-a.dev

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This plugin is provided as-is without any warranty. Use at your own risk.

## Changelog

### Version 1.0.0

- Initial release
- MTN Mobile Money integration
- Orange Money integration
- Webhook support
- Email and WhatsApp notifications
- Bilingual error messages (EN/FR)

## Frequently Asked Questions

**Q: Which countries does this plugin support?**
A: Currently, this plugin is designed for Cameroon, supporting MTN Mobile Money and Orange Money.

**Q: Do I need a Smobilpay account?**
A: Yes, you need a valid Smobilpay merchant account with API credentials.

**Q: Can I test payments in sandbox mode?**
A: Contact Smobilpay for sandbox credentials and update the API endpoints accordingly in the gateway classes.

**Q: Why aren't my customers receiving WhatsApp notifications?**
A: Ensure you've entered a valid WhatsApp API key in the gateway settings.

**Q: How do I customize the payment button text?**
A: Edit the "Title" field in the payment gateway settings under WooCommerce → Settings → Payments.

## Credits

Developed by Ange Arsene using the Smobilpay S3P API.
