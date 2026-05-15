<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AutoGopayClient
{
    public function generateQris(array $payload): array
    {
        return $this->request()->post('/qris/generate', $payload)->throw()->json() ?? [];
    }

    public function checkStatus(string $transactionId): array
    {
        return $this->request()->post('/qris/status', [
            'transaction_id' => $transactionId,
        ])->throw()->json() ?? [];
    }

    public function cancel(string $transactionId): array
    {
        return $this->request()->post('/qris/cancel', [
            'transaction_id' => $transactionId,
        ])->throw()->json() ?? [];
    }

    public function isValidSignature(string $payload, ?string $signature): bool
    {
        $secret = (string) config('services.autogopay.token');

        if ($secret === '' || $signature === null || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }

    private function request(): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.autogopay.base_url'), '/');
        $token = (string) config('services.autogopay.token');

        if ($token === '') {
            throw new RuntimeException('AUTOGOPAY_TOKEN belum dikonfigurasi.');
        }

        $request = Http::baseUrl($baseUrl)
            ->timeout((int) config('services.autogopay.timeout', 20))
            ->acceptJson()
            ->asJson()
            ->withToken($token);

        $caBundle = (string) config('services.autogopay.ca_bundle');

        if ($caBundle !== '' && is_file($caBundle)) {
            $request = $request->withOptions([
                'verify' => $caBundle,
            ]);
        }

        return $request;
    }
}
