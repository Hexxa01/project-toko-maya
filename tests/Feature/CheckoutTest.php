<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Services\OrderService;
use App\Services\ShippingService;
use Illuminate\Validation\ValidationException;
use Tests\Support\ShopTestCase;

class CheckoutTest extends ShopTestCase
{
    public function test_catalog_search_category_and_product_pages_render(): void
    {
        $this->get('/')->assertOk()->assertSee('Toko Maya');
        $this->get('/katalog?q=Mesin&category='.$this->product->category_id)->assertOk()->assertSee('Mesin Bor');
        $this->get('/produk/'.$this->product->id)->assertOk()->assertSee('25.000');
        $this->get('/katalog?q=TidakAda')->assertOk()->assertDontSee('Mesin Bor');
    }

    public function test_cart_quantity_validation_and_removal(): void
    {
        $this->post('/keranjang/'.$this->product->id, ['quantity' => 2])->assertSessionHas('cart.'.$this->product->id, 2);
        $this->get('/keranjang')->assertOk()->assertSee('50.000');
        $this->post('/keranjang/'.$this->product->id, ['quantity' => 20])->assertSessionHasErrors();
        $this->patch('/keranjang/'.$this->product->id, ['quantity' => 0])->assertSessionMissing('cart.'.$this->product->id);
    }

    public function test_checkout_ignores_client_totals_and_duplicate_submission_is_idempotent(): void
    {
        $d = $this->data(['total' => 1]);
        $this->actingAs($this->customer)->withSession(['cart' => [$this->product->id => 2]])->get('/checkout')->assertOk();
        $this->withSession(['cart' => [$this->product->id => 2]])->post('/checkout', $d)->assertRedirect();
        $o = Order::firstOrFail();
        $this->assertSame(50000, $o->total);
        $this->assertSame(8, $this->product->fresh()->stock);
        $this->withSession(['cart' => [$this->product->id => 2]])->post('/checkout', $d)->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(8, $this->product->fresh()->stock);
    }

    public function test_overselling_fails_without_partial_orders_or_stock_changes(): void
    {
        try {
            app(OrderService::class)->create([$this->product->id => 11], $this->data(), $this->customer->id);
            $this->fail('Must reject oversell');
        } catch (ValidationException $e) {
            $this->assertSame(10, $this->product->fresh()->stock);
            $this->assertDatabaseCount('orders', 0);
        }
        $o = $this->order();
        $this->assertSame(8, $this->product->fresh()->stock);
        try {
            app(OrderService::class)->create([$this->product->id => 9], $this->data(), $this->customer->id);
            $this->fail('Must reject reserved stock');
        } catch (ValidationException $e) {
            $this->assertDatabaseCount('orders', 1);
            $this->assertSame(8, $this->product->fresh()->stock);
        }
    }

    public function test_shipping_fee_uses_server_weight_and_cash_is_rejected(): void
    {
        config(['shop.shipping_rates' => ['JNE REG' => 10000]]);
        $o = $this->order(['fulfillment' => 'shipping', 'payment_method' => 'midtrans', 'courier' => 'JNE REG', 'address' => 'Padang', 'shipping_cost' => 1]);
        $this->assertSame(30000, $o->shipping_cost);
        $this->assertSame(80000, $o->total);
        $this->expectException(ValidationException::class);
        $this->order(['fulfillment' => 'shipping', 'courier' => 'JNE REG', 'address' => 'Padang']);
    }

    public function test_reviews_require_completed_purchase_and_only_one_review_per_item(): void
    {
        $o = $this->order();
        $i = $o->items->first();
        $d = ['rating' => 4, 'comment' => 'Barang bagus'];
        $this->actingAs($this->customer)->post('/ulasan/'.$i->id, $d)->assertForbidden();
        $o->update(['status' => 'completed', 'payment_status' => 'paid']);
        $this->post('/ulasan/'.$i->id, $d)->assertRedirect();
        $this->post('/ulasan/'.$i->id, [...$d, 'rating' => 5])->assertRedirect();
        $this->assertDatabaseCount('reviews', 1);
        $this->assertSame(5, Review::first()->rating);
    }

    public function test_import_dry_run_validation_and_atomic_no_overwrite(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'maya');
        file_put_contents($file, "sku,name,category,price,stock,weight,unit\nCSV-1,Palu,Perkakas,30000,5,500,pcs\n");
        try {
            $this->artisan('shop:import-products', ['file' => $file, '--dry-run' => true])->assertSuccessful();
            $this->assertDatabaseMissing('products', ['sku' => 'CSV-1']);
            $this->artisan('shop:import-products', ['file' => $file])->assertSuccessful();
            $this->assertDatabaseHas('products', ['sku' => 'CSV-1', 'stock' => 5]);
            $this->artisan('shop:import-products', ['file' => $file])->assertFailed();
        } finally {
            unlink($file);
        }
    }

    private function addressData(array $extra = []): array
    {
        return array_replace(['label' => 'Rumah', 'recipient' => 'Penerima', 'phone' => '0812345678', 'address' => 'Jalan Padang 1', 'zone' => 'padang', 'postal_code' => '25111'], $extra);
    }

    private function address(User $user, array $extra = []): Address
    {
        return $user->addresses()->create($this->addressData($extra));
    }

    public function test_address_book_lists_only_current_user_addresses(): void
    {
        $this->address($this->customer, ['label' => 'Rumah Sendiri']);
        $this->address($this->user('pelanggan'), ['label' => 'Alamat Rahasia']);
        $this->actingAs($this->customer)->get('/alamat')->assertOk()->assertSee('Rumah Sendiri')->assertDontSee('Alamat Rahasia');
    }

    public function test_first_saved_address_becomes_default(): void
    {
        $this->actingAs($this->customer)->post('/alamat', $this->addressData())->assertRedirect();
        $this->assertTrue($this->customer->addresses()->first()->is_default);
    }

    public function test_new_default_address_replaces_previous_default(): void
    {
        $first = $this->address($this->customer, ['is_default' => true]);
        $this->actingAs($this->customer)->post('/alamat', $this->addressData(['label' => 'Kantor', 'is_default' => 1]))->assertRedirect();
        $this->assertFalse($first->fresh()->is_default);
        $this->assertSame(1, $this->customer->addresses()->where('is_default', true)->count());
    }

    public function test_saved_address_can_be_edited(): void
    {
        $a = $this->address($this->customer);
        $this->actingAs($this->customer)->put('/alamat/'.$a->id, $this->addressData(['address' => 'Alamat Baru']))->assertRedirect();
        $this->assertSame('Alamat Baru', $a->fresh()->address);
    }

    public function test_customers_cannot_edit_other_users_addresses(): void
    {
        $a = $this->address($this->user('pelanggan'));
        $this->actingAs($this->customer)->put('/alamat/'.$a->id, $this->addressData())->assertForbidden();
    }

    public function test_customers_cannot_delete_other_users_addresses(): void
    {
        $a = $this->address($this->user('pelanggan'));
        $this->actingAs($this->customer)->delete('/alamat/'.$a->id)->assertForbidden();
        $this->assertDatabaseHas('addresses', ['id' => $a->id]);
    }

    public function test_deleting_default_address_promotes_another_address(): void
    {
        $a = $this->address($this->customer, ['is_default' => true]);
        $next = $this->address($this->customer, ['label' => 'Kantor']);
        $this->actingAs($this->customer)->delete('/alamat/'.$a->id)->assertRedirect();
        $this->assertTrue($next->fresh()->is_default);
    }

    public function test_address_validation_rejects_unsupported_zone_and_invalid_postcode(): void
    {
        $this->actingAs($this->customer)->post('/alamat', $this->addressData(['zone' => 'jakarta', 'postal_code' => '123']))->assertSessionHasErrors(['zone', 'postal_code']);
        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_checkout_uses_snapshot_of_selected_owned_address(): void
    {
        config(['shop.shipping_rates' => ['JNE REG' => 10000], 'shop.midtrans.server_key' => 'test-key']);
        $a = $this->address($this->customer, ['zone' => 'pariaman']);
        $this->actingAs($this->customer)->withSession(['cart' => [$this->product->id => 2]])->post('/checkout', $this->data(['address_id' => $a->id, 'fulfillment' => 'shipping', 'payment_method' => 'midtrans', 'courier' => 'JNE REG']))->assertRedirect();
        $o = Order::firstOrFail();
        $this->assertSame($a->address, $o->address);
        $this->assertSame(45000, $o->shipping_cost);
        $a->update(['address' => 'Berubah']);
        $this->assertNotSame($a->fresh()->address, $o->fresh()->address);
    }

    public function test_foreign_saved_address_is_rejected_at_checkout(): void
    {
        $a = $this->address($this->user('pelanggan'));
        $this->actingAs($this->customer)->withSession(['cart' => [$this->product->id => 1]])->post('/checkout', $this->data(['address_id' => $a->id]))->assertNotFound();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_invoice_codes_match_requested_format_and_are_unique(): void
    {
        $a = $this->order();
        $b = $this->order();
        $this->assertMatchesRegularExpression('/^ORD-\d{8}-[A-Z0-9]{4}$/', $a->code);
        $this->assertNotSame($a->code, $b->code);
    }

    public function test_shipping_estimate_changes_with_zone_and_rounded_weight(): void
    {
        config(['shop.shipping_rates' => ['JNE REG' => 10000]]);
        $service = app(ShippingService::class);
        $this->assertSame(10000, $service->quote('JNE REG', 'padang', 1));
        $this->assertSame(30000, $service->quote('JNE REG', 'pariaman', 1001));
        $this->expectException(ValidationException::class);
        $service->quote('JNE REG', 'outside', 1000);
    }
}
