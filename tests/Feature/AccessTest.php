<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ShopTestCase;

class AccessTest extends ShopTestCase
{
    public function test_registration_cannot_assign_staff_role_and_password_is_hashed(): void
    {
        $this->post('/daftar', ['name' => 'Baru', 'email' => 'baru@example.test', 'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'role' => 'pemilik'])->assertRedirect('/');
        $u = User::where('email', 'baru@example.test')->firstOrFail();
        $this->assertSame('pelanggan', $u->role);
        $this->assertTrue(Hash::check('Password123!', $u->password));
    }

    public function test_disabled_users_cannot_login_or_keep_staff_access(): void
    {
        $u = $this->user('pemilik');
        $u->forceFill(['active' => false])->save();
        $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->actingAs($u)->get('/pemilik')->assertForbidden();
        $this->post('/logout')->assertRedirect('/');
    }

    public function test_roles_have_separate_access_and_all_staff_pages_render(): void
    {
        foreach (['/pemilik', '/gudang', '/kasir'] as $url) {
            $this->actingAs($this->customer)->get($url)->assertForbidden();
        }
        $owner = $this->user('pemilik');
        foreach (['/pemilik', '/pemilik/produk', '/pemilik/produk/baru', '/pemilik/produk/'.$this->product->id.'/edit', '/pemilik/pengguna', '/gudang', '/kasir'] as $url) {
            $this->actingAs($owner)->get($url)->assertOk();
        }
        $this->actingAs($this->user('kasir'))->get('/gudang')->assertForbidden();
        $this->actingAs($this->user('admin_gudang'))->get('/kasir')->assertForbidden();
    }

    public function test_other_customers_cannot_view_pay_cancel_or_review_orders(): void
    {
        $o = $this->order();
        $other = $this->user('pelanggan');
        $this->actingAs($other)->get('/pesanan/'.$o->id)->assertForbidden();
        $this->post('/pesanan/'.$o->id.'/batal')->assertForbidden();
        $this->post('/pesanan/'.$o->id.'/bayar')->assertForbidden();
        $this->post('/ulasan/'.$o->items->first()->id, ['rating' => 5, 'comment' => 'Bagus'])->assertForbidden();
    }

    public function test_owner_cannot_disable_own_account_or_mass_assign_product_stock(): void
    {
        $owner = $this->user('pemilik');
        $this->actingAs($owner)->put('/pemilik/pengguna/'.$owner->id, ['name' => $owner->name, 'email' => $owner->email, 'role' => 'kasir', 'active' => 0])->assertSessionHasErrors();
        $d = ['name' => 'Baru', 'sku' => 'NEW', 'category_id' => $this->product->category_id, 'description' => 'Baru', 'price' => 50000, 'minimum_stock' => 5, 'weight' => 1000, 'unit' => 'pcs', 'active' => 1, 'stock' => 999];
        $this->post('/pemilik/produk', $d)->assertRedirect('/pemilik/produk');
        $this->assertDatabaseHas('products', ['sku' => 'NEW', 'stock' => 0]);
    }

    public function test_guests_are_redirected_before_checkout_or_staff_pages(): void
    {
        foreach (['/checkout', '/pemilik', '/gudang', '/kasir', '/alamat'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_invalid_registration_does_not_create_an_account(): void
    {
        $this->post('/daftar', ['name' => 'Baru', 'email' => 'invalid', 'password' => 'short', 'password_confirmation' => 'different'])->assertSessionHasErrors(['email', 'password']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_owner_can_create_staff_with_hashed_password(): void
    {
        $this->actingAs($this->user('pemilik'))->post('/pemilik/pengguna', ['name' => 'Staf Baru', 'email' => 'staf@example.test', 'password' => 'ValidPassword123', 'role' => 'admin_gudang', 'active' => 1])->assertRedirect();
        $staff = User::where('email', 'staf@example.test')->firstOrFail();
        $this->assertSame('admin_gudang', $staff->role);
        $this->assertTrue(Hash::check('ValidPassword123', $staff->password));
    }

    public function test_deactivation_blocks_existing_staff_session(): void
    {
        $staff = $this->user('kasir');
        $this->actingAs($staff)->get('/kasir')->assertOk();
        $staff->forceFill(['active' => false])->save();
        $this->get('/kasir')->assertForbidden();
    }

    public function test_owner_can_edit_product_and_disable_public_listing(): void
    {
        $this->actingAs($this->user('pemilik'))->put('/pemilik/produk/'.$this->product->id, ['name' => 'Bor Baru', 'sku' => 'TEST-01', 'category_id' => $this->product->category_id, 'description' => 'Spesifikasi baru', 'price' => 75000, 'minimum_stock' => 3, 'weight' => 1400, 'unit' => 'unit', 'active' => 0])->assertRedirect();
        $this->assertSame(75000, $this->product->fresh()->price);
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->get('/produk/'.$this->product->id)->assertNotFound();
    }
}
