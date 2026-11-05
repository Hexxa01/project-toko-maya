<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('label', 60);
            $t->string('recipient', 120);
            $t->string('phone', 25);
            $t->text('address');
            $t->string('zone', 50);
            $t->string('postal_code', 5);
            $t->boolean('is_default')->default(false);
            $t->timestamps();
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->string('shipping_zone', 50)->nullable();
            $t->string('postal_code', 5)->nullable();
            $t->text('snap_token')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('verified_by');
            $t->dropColumn(['shipping_zone', 'postal_code', 'snap_token', 'verified_at']);
        });
        Schema::dropIfExists('addresses');
    }
};
