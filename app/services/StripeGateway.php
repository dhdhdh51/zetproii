<?php
/**
 * Stripe gateway adapter using plain REST API calls (no stripe-php SDK
 * dependency required, per the "no Composer required for production"
 * constraint).
 */
final class StripeGateway implements PaymentGatewayInterface
{
    public function __construct(private string $secretKey, private string $webhookSecret)
    {
    }

    public function createOrder(float $amount, string $currency, string $receipt, array $metadata): array
    {
        $url = 'https://api.stripe.com/v1/payment_intents';
        $fields = [
            'amount' => (int) round($amount * 100),
            'currency' => strtolower($currency),
            'metadata' => $metadata,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->secretKey],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string) $body, true);
        if ($status >= 400 || !is_array($data)) {
            throw new RuntimeException('Stripe payment intent creation failed: ' . ($data['error']['message'] ?? "HTTP {$status}"));
        }

        return ['client_secret' => $data['client_secret'], 'payment_intent_id' => $data['id'], 'amount' => $data['amount'], 'currency' => $data['currency']];
    }

    public function verifyPayment(array $payload): bool
    {
        // Stripe confirms payment status server-side via webhook, not
        // client-signed payload like Razorpay - so this always defers to
        // webhook verification instead.
        return !empty($payload['payment_intent_status']) && $payload['payment_intent_status'] === 'succeeded';
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $sigHeader = $headers['Stripe-Signature'] ?? $headers['stripe-signature'] ?? '';
        if ($sigHeader === '') {
            return false;
        }
        $parts = [];
        foreach (explode(',', $sigHeader) as $part) {
            [$k, $v] = array_pad(explode('=', $part, 2), 2, '');
            $parts[$k] = $v;
        }
        $timestamp = $parts['t'] ?? '';
        $signature = $parts['v1'] ?? '';
        $signedPayload = "{$timestamp}.{$rawBody}";
        $expected = hash_hmac('sha256', $signedPayload, $this->webhookSecret);
        return hash_equals($expected, $signature);
    }

    public function refund(string $gatewayPaymentId, float $amount): array
    {
        $url = 'https://api.stripe.com/v1/refunds';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['payment_intent' => $gatewayPaymentId, 'amount' => (int) round($amount * 100)]),
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->secretKey],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode((string) $body, true);
        if ($status >= 400 || !is_array($data)) {
            throw new RuntimeException('Stripe refund failed: ' . ($data['error']['message'] ?? "HTTP {$status}"));
        }
        return $data;
    }
}
