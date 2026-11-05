<?php

namespace Tests\Feature;

use App\Services\OrderService;
use Illuminate\Support\Facades\Http;
use Tests\Support\ShopTestCase;

class PaymentTest extends ShopTestCase
{
    public function test_missing_payment_configuration_does_not_create_digital_order(): void
    {
        config(['shop.midtrans.server_key' => null]);
        $this->actingAs($this->customer)->withSession(['cart' => [$this->product->id => 1]])->post('/checkout', $this->data(['payment_method' => 'midtrans']))->assertSessionHasErrors();
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_midtrans_payment_redirect_and_duplicate_webhooks_are_safe(): void
    {
        config(['shop.midtrans.server_key' => 'test-server-key']);
        $o = $this->order(['payment_method' => 'midtrans']);
        Http::fake(['app.sandbox.midtrans.com/*' => Http::response(['token' => 'snap-test-token', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/test'], 201), 'api.sandbox.midtrans.com/*' => Http::response(['order_id' => $o->code, 'gross_amount' => '50000.00', 'transaction_status' => 'settlement', 'transaction_id' => 'tr-1'], 200)]);
        $this->actingAs($this->customer)->post('/pesanan/'.$o->id.'/bayar')->assertRedirect('https://app.sandbox.midtrans.com/snap/test');
        $d = ['order_id' => $o->code, 'status_code' => '200', 'gross_amount' => '50000.00', 'transaction_status' => 'settlement', 'transaction_id' => 'tr-1'];
        $d['signature_key'] = hash('sha512', $o->code.'20050000.00test-server-key');
        $this->postJson('/payments/midtrans/webhook', [...$d, 'signature_key' => 'fake'])->assertForbidden();
        $this->postJson('/payments/midtrans/webhook', $d)->assertOk();
        $this->postJson('/payments/midtrans/webhook', $d)->assertOk();
        $this->assertSame('paid', $o->fresh()->payment_status);
        $this->assertSame(8, $this->product->fresh()->stock);
        $this->postJson('/payments/midtrans/webhook', [...$d, 'transaction_status' => 'expire'])->assertOk();
        $this->assertSame('paid', $o->fresh()->payment_status);
    }

    public function test_signed_but_tampered_notification_cannot_override_gateway_pending_status(): void
    {
        config(['shop.midtrans.server_key' => 'test-server-key']);
        $o = $this->order(['payment_method' => 'midtrans']);
        Http::fake(['api.sandbox.midtrans.com/*' => Http::response(['order_id' => $o->code, 'gross_amount' => '50000.00', 'transaction_status' => 'pending', 'transaction_id' => 'tr-1'], 200)]);
        $this->postJson('/payments/midtrans/webhook', ['order_id' => $o->code, 'status_code' => '201', 'gross_amount' => '50000.00', 'transaction_status' => 'settlement', 'transaction_id' => 'tr-1', 'signature_key' => hash('sha512', $o->code.'20150000.00test-server-key')])->assertOk();
        $this->assertSame('pending', $o->fresh()->payment_status);
    }

    public function test_cash_order_cannot_be_paid_by_gateway_webhook(): void
    {
        config(['shop.midtrans.server_key' => 'test-server-key']);
        $o = $this->order();
        $this->postJson('/payments/midtrans/webhook', ['order_id' => $o->code, 'status_code' => '200', 'gross_amount' => '50000.00', 'transaction_status' => 'settlement', 'transaction_id' => 'tr-1', 'signature_key' => hash('sha512', $o->code.'20050000.00test-server-key')])->assertUnprocessable();
    }

    public function test_snap_json_endpoint_returns_token_without_marking_paid(): void
    {
        config(['shop.midtrans.server_key' => 'test-key', 'shop.midtrans.client_key' => 'public-client-key']);
        $o = $this->order(['payment_method' => 'midtrans']);
        Http::fake(['app.sandbox.midtrans.com/*' => Http::response(['token' => 'snap-token-123', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/test'], 201)]);
        $this->actingAs($this->customer)->postJson('/pesanan/'.$o->id.'/bayar')->assertOk()->assertJsonPath('token', 'snap-token-123');
        $this->assertSame('pending', $o->fresh()->payment_status);
        $this->assertSame(8, $this->product->fresh()->stock);
    }

    public function test_snap_popup_requires_client_key_and_fallback_redirect_still_works(): void
    {
        config(['shop.midtrans.server_key' => 'test-key', 'shop.midtrans.client_key' => null]);
        $o = $this->order(['payment_method' => 'midtrans']);
        $this->actingAs($this->customer)->postJson('/pesanan/'.$o->id.'/bayar')->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_gateway_redirect_to_untrusted_domain_is_rejected(): void
    {
        config(['shop.midtrans.server_key' => 'test-key']);
        $o = $this->order(['payment_method' => 'midtrans']);
        Http::fake(['app.sandbox.midtrans.com/*' => Http::response(['token' => 'bad', 'redirect_url' => 'https://evil.example/payment'], 201)]);
        $this->actingAs($this->customer)->post('/pesanan/'.$o->id.'/bayar')->assertSessionHasErrors('payment');
        $this->assertNull($o->fresh()->payment_url);
    }

    public function test_late_settlement_after_cancellation_cannot_restore_released_stock(): void
    {
        config(['shop.midtrans.server_key' => 'test-key']);
        $o = $this->order(['payment_method' => 'midtrans']);
        app(OrderService::class)->cancel($o);
        Http::fake(['api.sandbox.midtrans.com/*' => Http::response(['order_id' => $o->code, 'gross_amount' => '50000.00', 'transaction_status' => 'settlement', 'transaction_id' => 'late-1'])]);
        $d = ['order_id' => $o->code, 'status_code' => '200', 'gross_amount' => '50000.00', 'transaction_status' => 'settlement', 'transaction_id' => 'late-1', 'signature_key' => hash('sha512', $o->code.'20050000.00test-key')];
        $this->postJson('/payments/midtrans/webhook', $d)->assertUnprocessable();
        $this->assertSame('cancelled', $o->fresh()->payment_status);
        $this->assertSame(10, $this->product->fresh()->stock);
    }
}
