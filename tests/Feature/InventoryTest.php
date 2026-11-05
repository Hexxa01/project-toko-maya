<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ProductReturn;
use App\Services\OrderService;
use Tests\Support\ShopTestCase;

class InventoryTest extends ShopTestCase
{
    public function test_cash_payment_preparation_and_handover_do_not_deduct_stock_twice(): void
    {
        $o = $this->order();
        $cashier = $this->user('kasir');
        $warehouse = $this->user('admin_gudang');
        $this->actingAs($warehouse)->post('/gudang/pesanan/'.$o->id, ['action' => 'ready'])->assertUnprocessable();
        $this->actingAs($cashier)->post('/kasir/'.$o->id.'/tunai', ['cash_received' => 40000])->assertSessionHasErrors();
        $this->post('/kasir/'.$o->id.'/tunai', ['cash_received' => 60000])->assertRedirect();
        $this->assertSame('processing', $o->fresh()->status);
        $this->assertSame(8, $this->product->fresh()->stock);
        $this->actingAs($warehouse)->post('/gudang/pesanan/'.$o->id, ['action' => 'ready'])->assertRedirect();
        $this->actingAs($cashier)->post('/kasir/'.$o->id.'/serahkan')->assertRedirect();
        $this->post('/kasir/'.$o->id.'/serahkan')->assertUnprocessable();
        $this->assertSame('completed', $o->fresh()->status);
        $this->assertSame(8, $this->product->fresh()->stock);
        $this->get('/pesanan/'.$o->id)->assertOk()->assertSee('10.000');
    }

    public function test_cancellation_and_expiration_release_stock_once(): void
    {
        $o = $this->order();
        $this->actingAs($this->customer)->post('/pesanan/'.$o->id.'/batal')->assertRedirect();
        $this->post('/pesanan/'.$o->id.'/batal')->assertRedirect();
        $this->assertSame(10, $this->product->fresh()->stock);
        $expired = $this->order();
        $expired->update(['expires_at' => now()->subMinute()]);
        $this->artisan('shop:expire-orders')->assertSuccessful();
        $this->artisan('shop:expire-orders')->assertSuccessful();
        $this->assertSame('cancelled', $expired->fresh()->status);
        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_paid_orders_cannot_be_cancelled(): void
    {
        $o = $this->order();
        app(OrderService::class)->pay($o, 'cash', 50000);
        $this->actingAs($this->customer)->post('/pesanan/'.$o->id.'/batal')->assertSessionHasErrors();
        $this->assertSame(8, $this->product->fresh()->stock);
    }

    public function test_shipping_fulfillment_and_customer_receipt_require_valid_states(): void
    {
        config(['shop.shipping_rates' => ['JNE REG' => 10000]]);
        $o = $this->order(['fulfillment' => 'shipping', 'payment_method' => 'midtrans', 'courier' => 'JNE REG', 'address' => 'Padang']);
        app(OrderService::class)->pay($o, 'digital-paid');
        $this->actingAs($this->customer)->post('/pesanan/'.$o->id.'/terima')->assertUnprocessable();
        $warehouse = $this->user('admin_gudang');
        $this->actingAs($warehouse)->post('/gudang/pesanan/'.$o->id, ['action' => 'ship'])->assertSessionHasErrors();
        $this->post('/gudang/pesanan/'.$o->id, ['action' => 'ship', 'courier' => 'JNE REG', 'tracking_number' => 'RESI-123'])->assertRedirect();
        $this->get('/gudang')->assertOk()->assertSee('RESI-123');
        $this->post('/gudang/pesanan/'.$o->id, ['action' => 'tracking', 'courier' => 'JNE REG', 'tracking_number' => 'RESI-456'])->assertRedirect();
        $this->actingAs($this->customer)->get('/pesanan/'.$o->id)->assertOk()->assertSee('RESI-456');
        $this->post('/pesanan/'.$o->id.'/terima')->assertRedirect();
        $this->assertSame('completed', $o->fresh()->status);
        $this->assertSame(8, $this->product->fresh()->stock);
    }

    public function test_pos_cash_validates_tender_and_never_accepts_client_prices(): void
    {
        $u = $this->user('kasir');
        $d = $this->data(['items' => [$this->product->id => 2], 'cash_received' => 40000]);
        $this->actingAs($u)->post('/kasir/pos', $d)->assertSessionHasErrors();
        $this->assertDatabaseCount('orders', 0);
        $this->post('/kasir/pos', [...$d, 'cash_received' => 100000, 'price' => 1])->assertRedirect();
        $o = Order::firstOrFail();
        $this->assertSame('completed', $o->status);
        $this->assertSame(50000, $o->total);
        $this->assertSame(8, $this->product->fresh()->stock);
    }

    public function test_returns_limit_quantities_quarantine_damaged_items_and_log_refunds(): void
    {
        $o = $this->order();
        app(OrderService::class)->pay($o, 'cash', 50000);
        $o->update(['status' => 'completed']);
        $d = ['code' => $o->code, 'sku' => $this->product->sku, 'quantity' => 1, 'reason' => 'Cacat pabrik', 'restock' => 0];
        $this->actingAs($this->user('kasir'))->post('/kasir/retur', $d)->assertRedirect();
        $this->assertSame(8, $this->product->fresh()->stock);
        $this->post('/kasir/retur', [...$d, 'quantity' => 2])->assertSessionHasErrors();
        $this->post('/kasir/retur', [...$d, 'reason' => 'Kemasan utuh', 'restock' => 1])->assertRedirect();
        $this->assertSame(9, $this->product->fresh()->stock);
        $ret = ProductReturn::first();
        $this->actingAs($this->user('pemilik'))->post('/pemilik/retur/'.$ret->id.'/dana', ['refund_reference' => 'TRANSFER-1'])->assertRedirect();
        $this->assertSame('paid', $ret->fresh()->refund_status);
    }

    public function test_stock_adjustments_are_audited_and_cannot_go_negative(): void
    {
        $this->actingAs($this->user('admin_gudang'))->post('/gudang/stok/'.$this->product->id, ['change' => -11, 'reason' => 'Koreksi'])->assertSessionHasErrors();
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->post('/gudang/stok/'.$this->product->id, ['change' => 5, 'reason' => 'Penerimaan barang'])->assertRedirect();
        $this->assertSame(15, $this->product->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', ['change' => 5, 'reason' => 'Penerimaan barang']);
    }

    public function test_warehouse_verification_records_operator_without_deducting_stock(): void
    {
        $o = $this->order();
        app(OrderService::class)->pay($o, 'cash', 50000);
        $staff = $this->user('admin_gudang');
        $this->actingAs($staff)->post('/gudang/pesanan/'.$o->id, ['action' => 'verify'])->assertRedirect();
        $this->assertSame($staff->id, $o->fresh()->verified_by);
        $this->assertNotNull($o->fresh()->verified_at);
        $this->assertSame(8, $this->product->fresh()->stock);
    }

    public function test_delivery_note_contains_goods_without_prices(): void
    {
        $o = $this->order();
        app(OrderService::class)->pay($o, 'cash', 50000);
        $this->actingAs($this->user('admin_gudang'))->get('/gudang/'.$o->id.'/dokumen')->assertOk()->assertSee('SURAT JALAN')->assertSee('TEST-01')->assertDontSee('50.000');
    }

    public function test_shipping_label_contains_recipient_courier_and_tracking(): void
    {
        $o = $this->order();
        app(OrderService::class)->pay($o, 'cash', 50000);
        $o->update(['fulfillment' => 'shipping', 'address' => 'Jalan Padang', 'courier' => 'JNE REG', 'tracking_number' => 'RESI-TEST']);
        $this->actingAs($this->user('admin_gudang'))->get('/gudang/'.$o->id.'/dokumen?type=label')->assertOk()->assertSee('LABEL PAKET')->assertSee('Jalan Padang')->assertSee('RESI-TEST');
    }

    public function test_unpaid_orders_cannot_get_shipping_documents(): void
    {
        $o = $this->order();
        $this->actingAs($this->user('admin_gudang'))->get('/gudang/'.$o->id.'/dokumen')->assertUnprocessable();
        $this->actingAs($this->customer)->get('/gudang/'.$o->id.'/dokumen')->assertForbidden();
    }

    public function test_thermal_receipts_support_58_and_80mm_and_true_change(): void
    {
        $o = $this->order();
        app(OrderService::class)->pay($o, 'cash', 60000);
        $this->actingAs($this->user('kasir'));
        foreach ([58, 80] as $w) {
            $this->get('/pesanan/'.$o->id.'/struk?width='.$w)->assertOk()->assertSee('size:'.$w.'mm', false)->assertSee('10.000');
        }$this->get('/pesanan/'.$o->id.'/struk?width=100')->assertSessionHasErrors('width');
    }

    public function test_receipts_deny_other_customers_and_warehouse_staff(): void
    {
        $o = $this->order();
        $this->actingAs($this->user('pelanggan'))->get('/pesanan/'.$o->id.'/struk')->assertForbidden();
        $this->actingAs($this->user('admin_gudang'))->get('/pesanan/'.$o->id.'/struk')->assertForbidden();
    }

    public function test_pos_instant_lookup_matches_sku_and_preserves_access_control(): void
    {
        $this->actingAs($this->user('kasir'))->getJson('/kasir/cari-produk?q=TEST-01')->assertOk()->assertJsonPath('count', 1);
        $this->getJson('/kasir/cari-produk?q=missing')->assertOk()->assertJsonPath('count', 0);
        $this->actingAs($this->customer)->getJson('/kasir/cari-produk?q=TEST')->assertForbidden();
    }
}
