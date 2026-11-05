<?php

namespace Tests\Support;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class ShopTestCase extends TestCase
{
    use RefreshDatabase;

    protected Product $product;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $c = Category::create(['name' => 'Perkakas', 'slug' => 'perkakas']);
        $this->product = Product::create(['category_id' => $c->id, 'sku' => 'TEST-01', 'name' => 'Mesin Bor', 'description' => 'Bor untuk proyek', 'price' => 25000, 'stock' => 10, 'weight' => 1200]);
        $this->customer = $this->user('pelanggan');
    }

    protected function user(string $role): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => $role])->save();

        return $u;
    }

    protected function data(array $extra = []): array
    {
        return array_replace(['recipient' => 'Pembeli', 'phone' => '081234567890', 'fulfillment' => 'pickup', 'payment_method' => 'cash', 'request_key' => (string) Str::uuid()], $extra);
    }

    protected function order(array $extra = []): Order
    {
        return app(OrderService::class)->create([$this->product->id => 2], $this->data($extra), $this->customer->id);
    }
}
