<?php
/**
 * Contract every payment gateway adapter must implement. PaymentService
 * uses this abstraction so the rest of the app never talks to Razorpay/
 * Stripe/Cashfree SDKs directly - it always goes through PaymentService.
 */
interface PaymentGatewayInterface
{
    /**
     * Creates a payment/order on the gateway's side and returns whatever
     * data the frontend checkout widget needs (order id, key, etc.).
     */
    public function createOrder(float $amount, string $currency, string $receipt, array $metadata): array;

    /**
     * Verifies a client-submitted payment confirmation (signature check).
     */
    public function verifyPayment(array $payload): bool;

    /**
     * Verifies an inbound webhook's signature using the raw request body
     * and headers, returning the normalized event payload if valid.
     */
    public function verifyWebhookSignature(string $rawBody, array $headers): bool;

    public function refund(string $gatewayPaymentId, float $amount): array;
}
