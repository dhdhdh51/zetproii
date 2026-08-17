<?php
/**
 * Cashfree gateway adapter using plain REST API calls.
 */
final class CashfreeGateway implements PaymentGatewayInterface
{
    public function __construct(private string $appId, private string $secretKey)
    {
    }

    public function createOrder(float $amount, string $currency, string $receipt, array $metadata): array
    {
        $url = 'https://api.cashfree.com/pg/orders';
        $result = HttpClient::postJson($url, [
            'order_id' => $receipt,
            'order_amount' => $amount,
            'order_currency' => $currency,
            'order_note' => json_encode($metadata),
        ], [
            'x-client-id: ' . $this->appId,
            'x-client-secret: ' . $this->secretKey,
            'x-api-version: 2022-09-01',
        ]);

        $data = json_decode($result['body'], true);
        if ($result['status'] >= 400 || !is_array($data)) {
            throw new RuntimeException('Cashfree order creation failed: ' . ($data['message'] ?? "HTTP {$result['status']}"));
        }

        return ['order_id' => $data['order_id'] ?? $receipt, 'payment_session_id' => $data['payment_session_id'] ?? null];
    }

    public function verifyPayment(array $payload): bool
    {
        return ($payload['order_status'] ?? '') === 'PAID';
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $signature = $headers['x-webhook-signature'] ?? '';
        $timestamp = $headers['x-webhook-timestamp'] ?? '';
        $expected = base64_encode(hash_hmac('sha256', $timestamp . $rawBody, $this->secretKey, true));
        return hash_equals($expected, $signature);
    }

    public function refund(string $gatewayPaymentId, float $amount): array
    {
        $url = "https://api.cashfree.com/pg/orders/{$gatewayPaymentId}/refunds";
        $result = HttpClient::postJson($url, ['refund_amount' => $amount, 'refund_id' => 'rfnd_' . time()], [
            'x-client-id: ' . $this->appId,
            'x-client-secret: ' . $this->secretKey,
            'x-api-version: 2022-09-01',
        ]);
        $data = json_decode($result['body'], true);
        if ($result['status'] >= 400 || !is_array($data)) {
            throw new RuntimeException('Cashfree refund failed: ' . ($data['message'] ?? "HTTP {$result['status']}"));
        }
        return $data;
    }
}
