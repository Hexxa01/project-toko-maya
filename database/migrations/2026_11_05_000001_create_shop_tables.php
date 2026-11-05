<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('pelanggan')->index();
            $t->boolean('active')->default(true);
        });
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('slug')->unique();
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained()->restrictOnDelete();
            $t->string('sku')->unique();
            $t->string('name');
            $t->text('description');
            $t->unsignedBigInteger('price');
            $t->unsignedInteger('stock')->default(0);
            $t->unsignedInteger('minimum_stock')->default(5);
            $t->unsignedInteger('weight')->default(1000);
            $t->string('unit')->default('pcs');
            $t->string('brand')->nullable();
            $t->string('material')->nullable();
            $t->string('specification')->nullable();
            $t->string('warranty')->nullable();
            $t->string('image')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('cashier_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('channel')->default('online');
            $t->string('fulfillment');
            $t->string('status')->default('awaiting_payment')->index();
            $t->string('payment_status')->default('pending');
            $t->string('payment_method');
            $t->unsignedBigInteger('subtotal');
            $t->unsignedBigInteger('shipping_cost')->default(0);
            $t->unsignedBigInteger('total');
            $t->unsignedBigInteger('cash_received')->nullable();
            $t->string('recipient');
            $t->string('phone')->nullable();
            $t->text('address')->nullable();
            $t->string('courier')->nullable();
            $t->string('tracking_number')->nullable();
            $t->text('payment_url')->nullable();
            $t->string('payment_reference')->nullable();
            $t->boolean('stock_released')->default(false);
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('sku');
            $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('price');
            $t->unsignedInteger('returned_quantity')->default(0);
            $t->timestamps();
        });
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->integer('change');
            $t->string('reason');
            $t->timestamps();
        });
        Schema::create('product_returns', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('quantity');
            $t->text('reason');
            $t->boolean('restock');
            $t->unsignedBigInteger('refund_amount');
            $t->string('refund_status')->default('pending');
            $t->string('refund_reference')->nullable();
            $t->timestamps();
        });
        Schema::create('reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_item_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('rating');
            $t->text('comment');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['reviews', 'product_returns', 'stock_movements', 'order_items', 'orders', 'products', 'categories'] as $table) {
            Schema::dropIfExists($table);
        } Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'active']));
    }
};
