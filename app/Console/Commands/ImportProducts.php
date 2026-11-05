<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportProducts extends Command
{
    protected $signature = 'shop:import-products {file : CSV UTF-8} {--dry-run : Validate without saving}';

    protected $description = 'Import new catalog products atomically; never overwrite existing SKU or stock';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('Berkas tidak dapat dibaca.');

            return self::FAILURE;
        }
        $f = fopen($path, 'r');
        $header = fgetcsv($f);
        if (! $header) {
            fclose($f);
            $this->error('CSV kosong.');

            return self::FAILURE;
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        $required = ['sku', 'name', 'category', 'price', 'stock', 'weight', 'unit'];
        if (count($header) !== count(array_unique($header)) || array_diff($required, $header)) {
            fclose($f);
            $this->error('Header wajib: '.implode(',', $required));

            return self::FAILURE;
        }
        $rows = [];
        $seen = [];
        $line = 1;
        while (($cells = fgetcsv($f)) !== false) {
            $line++;
            if ($cells === [null]) {
                continue;
            }if (count($cells) !== count($header)) {
                fclose($f);
                $this->error("Jumlah kolom salah pada baris {$line}.");

                return self::FAILURE;
            }
            $d = array_combine($header, $cells);
            $v = Validator::make($d, ['sku' => 'required|string|max:80|unique:products', 'name' => 'required|string|max:180', 'category' => 'required|string|max:100', 'price' => 'required|integer|between:1,1000000000', 'stock' => 'required|integer|between:0,1000000', 'weight' => 'required|integer|between:1,1000000', 'unit' => 'required|string|max:30', 'description' => 'nullable|string|max:5000']);
            if ($v->fails() || isset($seen[$d['sku']])) {
                fclose($f);
                $this->error("Data tidak valid / SKU duplikat pada baris {$line}. ".$v->errors()->first());

                return self::FAILURE;
            }
            $seen[$d['sku']] = true;
            $rows[] = $d;
        }fclose($f);
        if (! $rows) {
            $this->error('Tidak ada produk untuk diimpor.');

            return self::FAILURE;
        }
        if (! $this->option('dry-run')) {
            DB::transaction(function () use ($rows) {
                foreach ($rows as $d) {
                    $c = Category::firstOrCreate(['name' => $d['category']], ['slug' => (Str::slug($d['category']) ?: 'kategori').'-'.Str::lower(Str::random(6))]);
                    $p = Product::create(['sku' => $d['sku'], 'name' => $d['name'], 'category_id' => $c->id, 'price' => $d['price'], 'stock' => $d['stock'], 'weight' => $d['weight'], 'unit' => $d['unit'], 'description' => $d['description'] ?? $d['name']]);
                    if ($p->stock) {
                        StockMovement::create(['product_id' => $p->id, 'change' => $p->stock, 'reason' => 'Saldo awal impor CSV']);
                    }
                }
            });
        }
        $this->info(count($rows).($this->option('dry-run') ? ' produk valid (tidak disimpan).' : ' produk berhasil diimpor.'));

        return self::SUCCESS;
    }
}
