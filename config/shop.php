<?php

return [
    'address' => 'Pasar Raya Barat, Kota Padang, Sumatera Barat',
    'hours' => 'Senin–Sabtu, 08.00–17.00 WIB',
    // Explicit shop tariffs per kg, not a live carrier quote. Empty until configured.
    'shipping_rates' => array_filter([
        'JNE REG' => env('SHIPPING_JNE_PER_KG'),
        'J&T REG' => env('SHIPPING_JNT_PER_KG'),
        'SiCepat REG' => env('SHIPPING_SICEPAT_PER_KG'),
    ], fn ($v) => filter_var($v, FILTER_VALIDATE_INT) !== false && $v > 0 && $v <= 1000000000),
    'shipping_zones' => [
        'padang' => ['name' => 'Kota Padang', 'multiplier' => 1],
        'padang-pariaman' => ['name' => 'Kabupaten Padang Pariaman', 'multiplier' => 1.5],
        'pariaman' => ['name' => 'Kota Pariaman', 'multiplier' => 1.5],
        'padang-panjang' => ['name' => 'Kota Padang Panjang', 'multiplier' => 2],
    ],
    'midtrans' => ['server_key' => env('MIDTRANS_SERVER_KEY'), 'client_key' => env('MIDTRANS_CLIENT_KEY'), 'production' => env('MIDTRANS_PRODUCTION', false)],
];
