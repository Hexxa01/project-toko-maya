<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class MidtransService
{
    public function status(Order $order): array
    {
        $key = config('shop.midtrans.server_key');
        abort_unless($key, 503);
        $host = config('shop.midtrans.production') ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
        $response = Http::withBasicAuth($key, '')->timeout(20)->get($host.'/v2/'.rawurlencode($order->code).'/status');
        abort_unless($response->successful(), 503, 'Verifikasi pembayaran belum tersedia.');
        $data = $response->json();
        abort_unless(is_array($data) && ($data['order_id'] ?? null) === $order->code && (float) ($data['gross_amount'] ?? -1) === (float) $order->total, 422);

        return $data;
    }

    public function start(Order $order): string
    {
        $key = config('shop.midtrans.server_key');
        if (! $key) {
            throw ValidationException::withMessages(['payment' => 'Pembayaran digital belum diaktifkan toko. Pesanan tetap tersimpan; silakan hubungi toko.']);
        }
        if ($order->payment_url && $order->snap_token) {
            return $order->payment_url;
        }
        $host = config('shop.midtrans.production') ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';
        $minutes = max(1, (int) now()->diffInMinutes($order->expires_at));
        $response = Http::withBasicAuth($key, '')->timeout(20)->post($host.'/snap/v1/transactions', [
            'transaction_details' => ['order_id' => $order->code, 'gross_amount' => $order->total],
            'customer_details' => ['first_name' => $order->recipient, 'email' => $order->user?->email, 'phone' => $order->phone],
            'expiry' => ['start_time' => now()->format('Y-m-d H:i:s O'), 'unit' => 'minutes', 'duration' => $minutes],
            'callbacks' => ['finish' => route('orders.show', $order)],
        ]);
        if (! $response->successful() || ! $response->json('redirect_url') || ! $response->json('token')) {
            throw ValidationException::withMessages(['payment' => 'Layanan pembayaran belum dapat dihubungi. Coba lagi dari detail pesanan.']);
        }
        $url = $response->json('redirect_url');
        $expected = config('shop.midtrans.production') ? 'app.midtrans.com' : 'app.sandbox.midtrans.com';
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== $expected) {
            throw ValidationException::withMessages(['payment' => 'Respons layanan pembayaran tidak valid.']);
        }
        $order->update(['payment_url' => $url, 'snap_token' => $response->json('token')]);

        return $url;
    }
}
