<?php
/**
 * Unified payment service (spec #29). Wraps whichever gateway is enabled
 * for the platform and exposes createPayment/verifyPayment/refundPayment/
 * handleWebhook so the rest of the app never depends on a specific
 * provider. Gateway credentials are read from encrypted `settings` rows
 * (admin-configurable), falling back to .env for local development.
 */
final class PaymentService
{
    public function createPayment(int $businessId, float $amount, string $currency, array $metadata = []): array
    {
        $gateway = $this->activeGateway();
        $receipt = 'biz' . $businessId . '_' . time();

        $uuid = $this->uuid4();
        Database::query(
            "INSERT INTO payments (uuid, business_id, gateway, amount, currency, status, metadata, created_at)
             VALUES (?, ?, ?, ?, ?, 'created', ?, NOW())",
            [$uuid, $businessId, $this->activeGatewaySlug(), $amount, $currency, json_encode($metadata)]
        );
        $paymentId = (int) Database::lastInsertId();

        try {
            $orderData = $gateway->createOrder($amount, $currency, $receipt, array_merge($metadata, ['payment_id' => $paymentId]));
        } catch (\Throwable $e) {
            Database::query("UPDATE payments SET status = 'failed' WHERE id = ?", [$paymentId]);
            Logger::payment('Payment order creation failed: ' . $e->getMessage());
            throw $e;
        }

        return array_merge($orderData, ['payment_id' => $paymentId, 'payment_uuid' => $uuid]);
    }

    public function verifyPayment(int $paymentId, array $payload): bool
    {
        $payment = Database::fetchOne("SELECT * FROM payments WHERE id = ?", [$paymentId]);
        if ($payment === null) {
            return false;
        }

        $gateway = $this->gatewayFor($payment['gateway']);
        $verified = $gateway->verifyPayment($payload);

        if ($verified) {
            Database::query(
                "UPDATE payments SET status = 'success', gateway_payment_id = ? WHERE id = ?",
                [$payload['razorpay_payment_id'] ?? $payload['payment_intent_id'] ?? $payload['cf_payment_id'] ?? null, $paymentId]
            );
            Database::query(
                "INSERT INTO transactions (payment_id, type, amount, status, gateway_reference, created_at) VALUES (?, 'charge', ?, 'success', ?, NOW())",
                [$paymentId, $payment['amount'], $payload['razorpay_payment_id'] ?? '']
            );
            AutomationService::trigger((int) $payment['business_id'], 'payment.completed', ['payment_id' => $paymentId]);
            Logger::payment("Payment #{$paymentId} verified successfully.");
        } else {
            Database::query("UPDATE payments SET status = 'failed' WHERE id = ?", [$paymentId]);
            Logger::payment("Payment #{$paymentId} verification failed.");
        }

        return $verified;
    }

    public function refundPayment(int $paymentId, ?float $amount = null): array
    {
        $payment = Database::fetchOne("SELECT * FROM payments WHERE id = ?", [$paymentId]);
        if ($payment === null || $payment['status'] !== 'success') {
            throw new RuntimeException('Only successful payments can be refunded.');
        }

        $gateway = $this->gatewayFor($payment['gateway']);
        $refundAmount = $amount ?? (float) $payment['amount'];
        $result = $gateway->refund($payment['gateway_payment_id'], $refundAmount);

        Database::query("UPDATE payments SET status = 'refunded' WHERE id = ?", [$paymentId]);
        Database::query(
            "INSERT INTO transactions (payment_id, type, amount, status, gateway_reference, created_at) VALUES (?, 'refund', ?, 'success', ?, NOW())",
            [$paymentId, $refundAmount, json_encode($result)]
        );
        Logger::payment("Payment #{$paymentId} refunded: {$refundAmount}");

        return $result;
    }

    /**
     * Handles an inbound webhook from any configured gateway. Verifies
     * the signature BEFORE trusting any of the payload (spec #29: "must
     * verify signatures").
     */
    public function handleWebhook(string $gatewaySlug, string $rawBody, array $headers): void
    {
        $gateway = $this->gatewayFor($gatewaySlug);

        if (!$gateway->verifyWebhookSignature($rawBody, $headers)) {
            Logger::payment("Webhook signature verification FAILED for gateway {$gatewaySlug}.");
            Response::forbidden('Invalid webhook signature.');
        }

        $payload = json_decode($rawBody, true) ?: [];
        Logger::payment("Webhook received from {$gatewaySlug}", ['event' => $payload['event'] ?? $payload['type'] ?? 'unknown']);

        // Normalize + process (gateway-specific payload shapes handled minimally;
        // extend per-gateway as real traffic patterns are observed in production).
        $gatewayPaymentId = $payload['payload']['payment']['entity']['id']
            ?? $payload['data']['object']['id']
            ?? $payload['data']['payment']['cf_payment_id']
            ?? null;

        if ($gatewayPaymentId !== null) {
            $payment = Database::fetchOne("SELECT id, business_id FROM payments WHERE gateway_payment_id = ? OR gateway = ?", [$gatewayPaymentId, $gatewaySlug]);
            if ($payment !== null) {
                Database::query("UPDATE payments SET status = 'success' WHERE id = ?", [$payment['id']]);
                AutomationService::trigger((int) $payment['business_id'], 'payment.completed', ['payment_id' => $payment['id']]);
            }
        }
    }

    private function activeGatewaySlug(): string
    {
        $row = Database::fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'active_payment_gateway'");
        return $row['setting_value'] ?? 'razorpay';
    }

    private function activeGateway(): PaymentGatewayInterface
    {
        return $this->gatewayFor($this->activeGatewaySlug());
    }

    private function gatewayFor(string $slug): PaymentGatewayInterface
    {
        return match ($slug) {
            'razorpay' => new RazorpayGateway(
                config('payments.razorpay.key_id', ''),
                config('payments.razorpay.key_secret', '')
            ),
            'stripe' => new StripeGateway(
                config('payments.stripe.secret_key', ''),
                config('payments.stripe.webhook_secret', '')
            ),
            'cashfree' => new CashfreeGateway(
                config('payments.cashfree.app_id', ''),
                config('payments.cashfree.secret_key', '')
            ),
            default => throw new RuntimeException("Unknown payment gateway: {$slug}"),
        };
    }

    private function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
