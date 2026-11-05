<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AddressController extends Controller
{
    public function index(Request $r)
    {
        return view('shop.addresses', ['addresses' => $r->user()->addresses()->orderByDesc('is_default')->latest()->get()]);
    }

    public function store(Request $r)
    {
        return $this->save($r);
    }

    public function update(Request $r, Address $address)
    {
        abort_unless($address->user_id === $r->user()->id, 403);

        return $this->save($r, $address);
    }

    private function save(Request $r, ?Address $address = null)
    {
        $d = $r->validate(['label' => 'required|string|max:60', 'recipient' => 'required|string|max:120', 'phone' => 'required|string|max:25', 'address' => 'required|string|max:1000', 'zone' => ['required', Rule::in(array_keys(config('shop.shipping_zones')))], 'postal_code' => 'required|digits:5', 'is_default' => 'sometimes|boolean']);
        DB::transaction(function () use ($r, $d, $address) {
            // Serialize default-address changes per user, including the first address.
            User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            $default = ($d['is_default'] ?? false) || ! $r->user()->addresses()->exists();
            if ($default) {
                $r->user()->addresses()->update(['is_default' => false]);
            }
            if ($address) {
                $address->update([...$d, 'is_default' => $default || $address->is_default]);
            } else {
                $r->user()->addresses()->create([...$d, 'is_default' => $default]);
            }
        });

        return back()->with('success', 'Alamat pengiriman disimpan.');
    }

    public function destroy(Request $r, Address $address)
    {
        abort_unless($address->user_id === $r->user()->id, 403);
        DB::transaction(function () use ($r, $address) {
            User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            $wasDefault = $address->is_default;
            $address->delete();
            if ($wasDefault) {
                $next = $r->user()->addresses()->oldest('id')->first();
                $next?->update(['is_default' => true]);
            }
        });

        return back()->with('success','Alamat dihapus.');
    }
}
