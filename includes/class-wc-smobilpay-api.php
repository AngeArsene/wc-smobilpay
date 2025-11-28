<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class WC_Smobilpay_API
{

    private $api_url = 'https://s3p.smobilpay.staging.maviance.info/v2';
    private $merchant_key;
    private $secret_key; // S3P secret_key
    private $client;

    public function __construct($merchant_key, $secret_key)
    {
        $this->merchant_key = $merchant_key;
        $this->secret_key = $secret_key;
        $this->client = new Client(); // Guzzle client
    }

    /**
     * Generate s3pAuth header
     */
    private function generateS3PAuthHeader(
        string $method,
        string $path,
        array $queryParams = [],
        array $bodyParams = []
    ) {
        $timestamp = round(microtime(true) * 1000);
        $nonce     = round(microtime(true) * 1000);

        $s3pParams = [
            "s3pAuth_nonce" => $nonce,
            "s3pAuth_timestamp" => $timestamp,
            "s3pAuth_signature_method" => "HMAC-SHA1",
            "s3pAuth_token" => $this->merchant_key,
        ];

        $params = array_merge($queryParams, $bodyParams, $s3pParams);

        foreach ($params as $k => $v) {
            if (is_string($v)) $params[$k] = trim($v);
        }

        ksort($params);

        // ❗ Match Postman's non-encoded style
        $parameterString = implode('&', array_map(
            fn($k, $v) => $k . '=' . $v,
            array_keys($params),
            $params
        ));

        $url = rtrim($this->api_url, '/') . '/' . ltrim($path, '/');

        $baseString = $method . "&"
            . rawurlencode($url) . "&"
            . rawurlencode($parameterString);

        $signature = base64_encode(hash_hmac('sha1', $baseString, $this->secret_key, true));

        return "s3pAuth "
            . "s3pAuth_timestamp=\"{$timestamp}\", "
            . "s3pAuth_signature=\"{$signature}\", "
            . "s3pAuth_nonce=\"{$nonce}\", "
            . "s3pAuth_signature_method=\"HMAC-SHA1\", "
            . "s3pAuth_token=\"{$this->merchant_key}\"";
    }


    /**
     * Make GET request
     */
    private function send_get($path, $queryParams = [])
    {
        $authHeader = $this->generateS3PAuthHeader("GET", $path, $queryParams);

        $url = $this->api_url . $path;
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $request = new Request("GET", $url, [
            "Authorization" => $authHeader,
            "Content-Type" => "application/json"
        ]);

        $response = $this->client->send($request, ['timeout' => 30]);

        return $this->handle_response($response);
    }

    /**
     * Make POST request
     */
    private function send_post($path, $bodyParams = [])
    {
        $authHeader = $this->generateS3PAuthHeader("POST", $path, [], $bodyParams);

        $request = new Request("POST", $this->api_url . $path, [
            "Authorization" => $authHeader,
            "Content-Type" => "application/json"
        ], json_encode($bodyParams));

        $response = $this->client->send($request, ['timeout' => 30]);

        return $this->handle_response($response);
    }

    // === Public API methods ===

    public function get_payable_item($payment_item)
    {
        return $this->send_get('/cashout', ['serviceid' => $payment_item]);
    }

    public function get_payment_options($payable_item_id)
    {
        return $this->send_get('/cashout/' . $payable_item_id);
    }

    public function initiate_transaction($quote_data)
    {
        return $this->send_post('/quotestd', $quote_data);
    }

    public function finalize_transaction($collect_data)
    {
        return $this->send_post('/collectstd', $collect_data);
    }

    public function verify_transaction($ptn)
    {
        return $this->send_get('/verifytx/' . $ptn);
    }

    // === Handle API response ===
    private function handle_response($response)
    {
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);
        $status_code = $response->getStatusCode();

        // Save readable JSON for debugging
        if (!empty($data)) {
            file_put_contents(__DIR__ . '/data.json', json_encode($data, JSON_PRETTY_PRINT));
        }

        if ($status_code >= 200 && $status_code < 300) {
            return [
                'success' => true,
                'data' => $data,
            ];
        }

        return [
            'success' => false,
            'message' => 'Error: ' . $data['message'] ?? 'API request failed',
            'data' => $data,
        ];
    }
}
