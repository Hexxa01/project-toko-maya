<?php

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('shop:expire-orders', function (OrderService $service) {
    $count = 0;
    Order::where('payment_status', 'pending')->where('status', 'awaiting_payment')->where('expires_at', '<=', now())->orderBy('id')->chunkById(100, function ($orders) use ($service, &$count) {
        foreach ($orders as $order) {
            DB::transaction(function () use ($service, $order, &$count) {
                $o = Order::lockForUpdate()->findOrFail($order->id);
                if ($o->payment_status === 'pending' && $o->status === 'awaiting_payment') {
                    $service->cancel($o);
                    $count++;
                }
            });
        }
    });
    $this->info("{$count} pesanan kedaluwarsa dibatalkan.");
})->purpose('Release stock reservations for unpaid expired orders');
Schedule::command('shop:expire-orders')->everyMinute()->withoutOverlapping();
