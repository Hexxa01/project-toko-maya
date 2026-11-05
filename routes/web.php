<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ShopController::class, 'home'])->name('home');
Route::get('/katalog', [ShopController::class, 'catalog'])->name('catalog');
Route::get('/produk/{product}', [ShopController::class, 'show'])->name('products.show');
Route::get('/keranjang', [ShopController::class, 'cart'])->name('cart');
Route::post('/keranjang/{product}', [ShopController::class, 'add'])->name('cart.add');
Route::patch('/keranjang/{product}', [ShopController::class, 'update'])->name('cart.update');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'form'])->name('login');
    Route::get('/daftar', [AuthController::class, 'form'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/daftar', [AuthController::class, 'register'])->middleware('throttle:5,1');
});
Route::post('/payments/midtrans/webhook', [OrderController::class, 'webhook'])->middleware('throttle:120,1')->name('payments.webhook');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::middleware(['auth', 'role:pelanggan,kasir,admin_gudang,pemilik'])->group(function () {
    Route::get('/alamat', [AddressController::class, 'index'])->name('addresses.index');
    Route::post('/alamat', [AddressController::class, 'store'])->name('addresses.store');
    Route::put('/alamat/{address}', [AddressController::class, 'update'])->name('addresses.update');
    Route::delete('/alamat/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');
    Route::get('/pesanan/{order}/struk', [DocumentController::class, 'receipt'])->name('orders.receipt');
    Route::get('/dashboard', [AuthController::class, 'dashboard'])->name('dashboard');
    Route::get('/checkout', [OrderController::class, 'checkout'])->name('checkout');
    Route::post('/checkout', [OrderController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
    Route::get('/pesanan', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/pesanan/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/pesanan/{order}/bayar', [OrderController::class, 'payment'])->middleware('throttle:5,1')->name('orders.payment');
    Route::post('/pesanan/{order}/batal', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/pesanan/{order}/terima', [OrderController::class, 'complete'])->name('orders.complete');
    Route::post('/ulasan/{item}', [OrderController::class, 'review'])->name('reviews.store');
    Route::middleware('role:admin_gudang,pemilik')->group(function () {
        Route::get('/gudang/{order}/dokumen', [DocumentController::class, 'shipping'])->name('warehouse.document');
        Route::get('/gudang', [StaffController::class, 'warehouse'])->name('warehouse.index');
        Route::post('/gudang/stok/{product}', [StaffController::class, 'stock'])->name('warehouse.stock');
        Route::post('/gudang/pesanan/{order}', [StaffController::class, 'fulfill'])->name('warehouse.fulfill');
    });
    Route::middleware('role:kasir,pemilik')->group(function () {
        Route::get('/kasir/cari-produk', [StaffController::class, 'search'])->name('cashier.search');
        Route::get('/kasir', [StaffController::class, 'cashier'])->name('cashier.index');
        Route::post('/kasir/pos', [StaffController::class, 'pos'])->name('cashier.pos');
        Route::post('/kasir/{order}/tunai', [StaffController::class, 'cash'])->name('cashier.cash');
        Route::post('/kasir/{order}/serahkan', [StaffController::class, 'handover'])->name('cashier.handover');
        Route::post('/kasir/retur', [StaffController::class, 'returns'])->name('cashier.returns');
    });
    Route::prefix('pemilik')->name('owner.')->middleware('role:pemilik')->group(function () {
        Route::get('/', [OwnerController::class, 'index'])->name('index');
        Route::get('/laporan.xlsx', [DocumentController::class, 'xlsx'])->name('xlsx');
        Route::get('/laporan.pdf', [DocumentController::class, 'report'])->name('pdf');
        Route::get('/laporan.csv', [OwnerController::class, 'export'])->name('export');
        Route::get('/produk', [OwnerController::class, 'products'])->name('products');
        Route::get('/produk/baru', [OwnerController::class, 'form'])->name('products.create');
        Route::get('/produk/{product}/edit', [OwnerController::class, 'form'])->name('products.edit');
        Route::post('/produk', [OwnerController::class, 'save'])->name('products.store');
        Route::put('/produk/{product}', [OwnerController::class, 'save'])->name('products.update');
        Route::post('/kategori', [OwnerController::class, 'category'])->name('categories.store');
        Route::get('/pengguna', [OwnerController::class, 'users'])->name('users');
        Route::post('/pengguna', [OwnerController::class, 'saveUser'])->name('users.store');
        Route::put('/pengguna/{user}', [OwnerController::class, 'saveUser'])->name('users.update');
        Route::post('/retur/{return}/dana', [OwnerController::class, 'refund'])->name('refund');
    });
});
