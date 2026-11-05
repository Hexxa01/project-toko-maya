<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->uuid('request_key')->nullable()->unique());
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropUnique(['request_key']);
            $t->dropColumn('request_key');
        });
    }
};
