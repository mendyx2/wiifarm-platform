<?php

namespace App\Services\Payments;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Talks to Paystack (https://paystack.com/docs/api). Amounts are in kobo, as
 * Paystack expects. Only the secret key is needed: the member pays on
 * Paystack's own page and comes back.
 */
class Paystack
{
    public function configured(): bool
    {
        return filled(config('services.paystack.secret_key'));
    }

    /**
     * Starts a payment and returns the page to send the payer to.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function initialize(string $email, int $amount, string $reference, string $callbackUrl, array $metadata = []): string
    {
        $body = $this->call(fn (PendingRequest $http) => $http->post('/transaction/initialize', [
            'email' => $email,
            'amount' => $amount,
            'currency' => 'NGN',
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
        ]));

        $url = $body['data']['authorization_url'] ?? null;
        if (! is_string($url) || ! str_starts_with($url, 'https://')) {
            throw new PaymentException('Paystack did not return a payment page. Please try again.');
        }

        return $url;
    }

    /**
     * What Paystack says about a payment: status ("success", "failed",
     * "abandoned", ...), amount in kobo, currency and channel.
     *
     * @return array{status: string, amount: int, currency: string, channel: ?string, reference: string}
     */
    public function verify(string $reference): array
    {
        $body = $this->call(fn (PendingRequest $http) => $http->get('/transaction/verify/'.rawurlencode($reference)));

        return self::facts($body['data'] ?? []);
    }

    /** Is this webhook really from Paystack? It signs the raw body with the secret key. */
    public function validSignature(string $payload, ?string $signature): bool
    {
        $secret = (string) config('services.paystack.secret_key');

        return $secret !== '' && $signature !== null && hash_equals(hash_hmac('sha512', $payload, $secret), $signature);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: string, amount: int, currency: string, channel: ?string, reference: string}
     */
    public static function facts(array $data): array
    {
        return [
            'status' => (string) ($data['status'] ?? ''),
            'amount' => (int) ($data['amount'] ?? 0),
            'currency' => (string) ($data['currency'] ?? ''),
            'channel' => isset($data['channel']) ? (string) $data['channel'] : null,
            'reference' => (string) ($data['reference'] ?? ''),
        ];
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     * @return array<string, mixed>
     */
    private function call(callable $request): array
    {
        if (! $this->configured()) {
            throw new PaymentException('Online payment is not set up yet. Please try again later.');
        }

        $http = Http::baseUrl((string) config('services.paystack.base_url'))
            ->withToken((string) config('services.paystack.secret_key'))
            ->acceptJson()->timeout(20);

        try {
            $response = $request($http);
        } catch (ConnectionException) {
            throw new PaymentException('Paystack could not be reached. Please try again.');
        } catch (RequestException $e) {
            $response = $e->response; // a refusal: read Paystack's message below
        }

        $body = $response->json() ?? [];
        if (! $response->successful() || ($body['status'] ?? false) !== true) {
            throw new PaymentException(isset($body['message']) ? 'Paystack said: '.$body['message'].'.' : 'Paystack did not accept the request. Please try again.');
        }

        return $body;
    }
}
