<?php
/**
 * Razorpay gateway adapter. Uses Razorpay's plain REST API via
 * HttpClient - no SDK/Composer dependency required.
 */
final class RazorpayGateway implements PaymentGatewayInterface
{
    public function __construct(private string $keyId, private string $keySecret)
    {
    }

    public function createOrder(float $amount, string $currency, string $receipt, array $metadata): array
    {
        $url = 'https://api.razorpay.com/v1/orders';
        $result = HttpClient::postJson($url, [
            'amount' => (int) round($amount * 100), // paise
            'currency' => $currency,
            'receipt' => $receipt,
            'notes' => $metadata,
        ], [
            'Authorization: Basic ' . base64_encode("{$this->keyId}:{$this->keySecret}"),
        ]);

        $data = json_decode($result['body'], true);
        if ($result['status'] >= 400 || !is_array($data)) {
            throw new RuntimeException('Razorpay order creation failed: ' . ($data['error']['description'] ?? "HTTP {$result['status']}"));
        }

        return ['order_id' => $data['id'], 'key_id' => $this->keyId, 'amount' => $data['amount'], 'currency' => $data['currency']];
    }

    public function verifyPayment(array $payload): bool
    {
        // Razorpay's checkout signature: HMAC-SHA256(order_id + "|" + payment_id, key_secret)
        $expected = hash_hmac('sha256', $payload['razorpay_order_id'] . '|' . $payload['razorpay_payment_id'], $this->keySecret);
        return hash_equals($expected, $payload['razorpay_signature'] ?? '');
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $signature = $headers['X-Razorpay-Signature'] ?? $headers['x-razorpay-signature'] ?? '';
        $expected = hash_hmac('sha256', $rawBody, $this->keySecret);
        return hash_equals($expected, $signature);
    }

    public function refund(string $gatewayPaymentId, float $amount): array
    {
        $url = "https://api.razorpay.com/v1/payments/{$gatewayPaymentId}/refund";
        $result = HttpClient::postJson($url, ['amount' => (int) round($amount * 100)], [
            'Authorization: Basic ' . base64_encode("{$this->keyId}:{$this->keySecret}"),
        ]);
        $data = json_decode($result['body'], true);
        if ($result['status'] >= 400 || !is_array($data)) {
            throw new RuntimeException('Razorpay refund failed: ' . ($data['error']['description'] ?? "HTTP {$result['status']}"));
        }
        return $data;
    }
}
