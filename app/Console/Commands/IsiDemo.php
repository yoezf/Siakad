<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('demo:isi {--ya : Lewati pertanyaan konfirmasi}')]
#[Description('Mengosongkan database lalu mengisi data demo (dua sekolah, akun semua peran). Hanya untuk lokal atau demo.')]
class IsiDemo extends Command
{
    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Perintah ini ditolak di lingkungan produksi karena menghapus seluruh data.');

            return self::FAILURE;
        }
        $db = DB::connection()->getDatabaseName();
        if (! $this->option('ya') && ! $this->confirm("Ini MENGHAPUS SEMUA DATA di database \"{$db}\" lalu mengisi data demo. Lanjut?")) {
            $this->warn('Dibatalkan. Tidak ada yang diubah.');

            return self::FAILURE;
        }

        $mulai = microtime(true);
        $this->call('migrate:fresh', ['--force' => true]);
        $this->callSilent('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);
        $seeder = new DemoSeeder;
        $seeder->setContainer(app())->setCommand($this);
        $seeder->run();

        $this->newLine();
        $this->info(sprintf('Data demo siap dalam %.0f detik. Kata sandi semua akun: %s', microtime(true) - $mulai, DemoSeeder::SANDI));
        $this->table(['Sekolah', 'Peran', 'Nama', 'Email'], array_map(fn ($a) => [$a['sekolah'], $a['peran'], $a['nama'], $a['email']], $seeder->daftarAkun()));

        return self::SUCCESS;
    }
}
