<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateOwner extends Command
{
    protected $signature = 'shop:create-owner {email} {--name=Pemilik Toko Maya}';

    protected $description = 'Create the first owner account using a hidden password prompt';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->secret('Kata sandi pemilik (minimal 12 karakter)');
        $v = Validator::make(['email' => $email, 'password' => $password, 'name' => $this->option('name')], ['email' => 'required|email|max:255|unique:users', 'name' => 'required|string|max:120', 'password' => ['required', Password::min(12)]]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $u = new User;
        $u->forceFill(['name' => $this->option('name'), 'email' => $email, 'password' => $password, 'role' => 'pemilik', 'active' => true])->save();
        $this->info('Akun pemilik dibuat.');

        return self::SUCCESS;
    }
}
