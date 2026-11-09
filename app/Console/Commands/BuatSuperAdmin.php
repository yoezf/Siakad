<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Peran;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:buat-super-admin {nama} {email}')]
#[Description('Membuat akun Super Admin (tim developer); kata sandi ditanyakan, tidak disimpan di riwayat perintah')]
class BuatSuperAdmin extends Command
{
    public function handle(): int
    {
        if (User::where('email', $this->argument('email'))->exists()) {
            $this->error('Email itu sudah terdaftar.');

            return self::FAILURE;
        }
        $password = $this->secret('Kata sandi (minimal 12 karakter)');
        if (strlen((string) $password) < 12) {
            $this->error('Kata sandi terlalu pendek.');

            return self::FAILURE;
        }

        $this->callSilent('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);
        $user = new User(['name' => $this->argument('nama'), 'email' => $this->argument('email'), 'password' => $password]);
        $user->save(); // tanpa school_id: Super Admin tidak terikat sekolah
        $user->assignRole(Peran::SUPER_ADMIN);
        $this->info('Super Admin dibuat.');

        return self::SUCCESS;
    }
}
