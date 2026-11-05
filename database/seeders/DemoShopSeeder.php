<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoShopSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo seeder hanya untuk local/testing.');
        }
        $password = env('DEMO_PASSWORD');
        if (! $password || strlen($password) < 12) {
            throw new \RuntimeException('Atur DEMO_PASSWORD lokal minimal 12 karakter sebelum menjalankan demo seeder.');
        }
        foreach (['pemilik' => 'Pemilik Demo', 'kasir' => 'Kasir Demo', 'admin_gudang' => 'Gudang Demo', 'pelanggan' => 'Pelanggan Demo'] as $role => $name) {
            if (! User::where('email', $role.'@demo.tokomaya.test')->exists()) {
                $u = new User;
                $u->forceFill(['name' => $name, 'email' => $role.'@demo.tokomaya.test', 'password' => $password, 'role' => $role, 'active' => true])->save();
            }
        }
        $categories = ['Perkakas Tangan', 'Peralatan Bengkel', 'Aksesori Mesin Bor', 'Perlengkapan Bangunan', 'Lem, Sealant & Pelumas', 'Mesin Teknik', 'Baut & Mur', 'Alat Ukur', 'Peralatan Listrik', 'Keselamatan Kerja', 'Peralatan Las', 'Alat Potong', 'Peralatan Cat', 'Perlengkapan Pompa'];
        foreach ($categories as $i => $name) {
            Category::firstOrCreate(['slug' => 'demo-'.($i + 1)], ['name' => $name.' (Demo)']);
        }
        $items = [['Adaptor Bor Quick Change', 25000, 2], ['Adaptor Set Mata Bor', 30000, 2], ['Kunci Sok Universal', 35000, 1], ['Mesin Bor Listrik 13 mm', 325000, 5], ['Gerinda Tangan 4 Inci', 375000, 5], ['Tang Kombinasi 8 Inci', 55000, 0], ['Palu Konde 500 gram', 45000, 0], ['Meteran Baja 5 Meter', 28000, 7], ['Sealant Silikon 300 ml', 32000, 4], ['Alat Tembak Sealant', 30000, 4], ['Obeng Set Presisi', 65000, 0], ['Sarung Tangan Kerja', 18000, 9], ['Baut Hex M10', 2500, 6], ['Kuas Cat 3 Inci', 16000, 12], ['Mata Bor Besi Set', 48000, 2], ['Kunci Inggris 10 Inci', 85000, 0]];
        foreach ($items as $i => [$name,$price,$cat]) {
            $p = Product::firstOrCreate(['sku' => 'DEMO-'.str_pad($i + 1, 3, '0', STR_PAD_LEFT)], ['category_id' => Category::where('slug', 'demo-'.($cat + 1))->value('id'), 'name' => $name.' (Demo)', 'description' => 'Data contoh untuk pengujian Toko Maya. Harga, stok, dan spesifikasi bukan katalog resmi toko.', 'price' => $price, 'stock' => $i === 12 ? 3 : 20 + $i, 'minimum_stock' => 5, 'weight' => 1000, 'unit' => 'pcs', 'brand' => 'Contoh', 'specification' => 'Spesifikasi demo; periksa produk asli sebelum penjualan.']);
            if ($p->wasRecentlyCreated) {
                StockMovement::create(['product_id' => $p->id, 'change' => $p->stock, 'reason' => 'Saldo awal demo']);
            }
        }
    }
}
