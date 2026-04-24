<?php
declare(strict_types=1);

class Payment
{
    // Create a Stripe PaymentIntent via raw HTTP (no SDK required)
    public function createStripePaymentIntent(int $amountCents, string $currency, array $metadata = []): array
    {
        $params = [
            'amount'   => $amountCents,
            'currency' => strtolower($currency),
            'automatic_payment_methods[enabled]' => 'true',
        ];

        foreach ($metadata as $k => $v) {
            $params["metadata[{$k}]"] = $v;
        }

        return $this->stripeRequest('POST', '/v1/payment_intents', $params);
    }

    public function retrievePaymentIntent(string $piId): array
    {
        return $this->stripeRequest('GET', "/v1/payment_intents/{$piId}");
    }

    public function verifyWebhookSignature(string $payload, string $sigHeader): array
    {
        $secret = STRIPE_WEBHOOK_SECRET;
        $tolerance = 300; // 5 minute

        // Parse sig header: t=timestamp,v1=sig,...
        $parts = [];
        foreach (explode(',', $sigHeader) as $part) {
            [$k, $v] = array_pad(explode('=', $part, 2), 2, '');
            $parts[$k][] = $v;
        }

        if (empty($parts['t']) || empty($parts['v1'])) {
            throw new RuntimeException('Missing Stripe signature components');
        }

        $timestamp = (int) $parts['t'][0];
        if (abs(time() - $timestamp) > $tolerance) {
            throw new RuntimeException('Stripe webhook timestamp too old');
        }

        $signedPayload = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        $valid = false;
        foreach ($parts['v1'] as $sig) {
            if (hash_equals($expected, $sig)) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            throw new RuntimeException('Stripe webhook signature mismatch');
        }

        $event = json_decode($payload, true);
        if (!$event) {
            throw new RuntimeException('Invalid JSON in webhook payload');
        }

        return $event;
    }

    private function stripeRequest(string $method, string $endpoint, array $params = []): array
    {
        $url = 'https://api.stripe.com' . $endpoint;
        $ch  = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => STRIPE_SECRET_KEY . ':',
            CURLOPT_HTTPHEADER     => [
                'Stripe-Version: ' . STRIPE_API_VERSION,
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        } elseif ($method === 'GET' && !empty($params)) {
            curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException("Stripe cURL error: {$error}");
        }

        $data = json_decode($response, true);
        if (!$data) {
            throw new RuntimeException("Invalid Stripe response");
        }

        if ($httpCode >= 400) {
            $msg = $data['error']['message'] ?? "Stripe HTTP {$httpCode}";
            throw new RuntimeException("Stripe API error: {$msg}");
        }

        return $data;
    }
}
