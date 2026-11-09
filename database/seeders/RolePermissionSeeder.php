<?php

namespace Database\Seeders;

use App\Support\Peran;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = array_merge([Peran::SUPER_ADMIN], array_keys(Peran::SEKOLAH));
        foreach ($roles as $nama) {
            Role::findOrCreate($nama, 'web');
        }

        // Izin diperluas per modul pada tahap berikutnya.
        $izin = [
            'kelola-sekolah' => [Peran::SUPER_ADMIN],
            'kelola-pengguna' => [Peran::ADMIN_SEKOLAH],
            'kelola-profil-sekolah' => [Peran::ADMIN_SEKOLAH],
            'kelola-tahun-ajaran' => [Peran::ADMIN_SEKOLAH],
            'lihat-tahun-ajaran' => Peran::PANEL_SEKOLAH, // semua staf boleh melihat, hanya admin yang mengubah
            'lihat-data-master' => Peran::PANEL_SEKOLAH, // mata pelajaran dan kelas
            'kelola-data-master' => [Peran::ADMIN_SEKOLAH, Peran::STAF_TU],
            // Data pribadi (siswa, guru, orang tua): akses minimum. Guru dan wali kelas memperoleh akses
            // terbatas pada kelasnya sendiri setelah penugasan kelas ada (Tahap 4).
            'lihat-data-pribadi' => [Peran::ADMIN_SEKOLAH, Peran::KEPALA_SEKOLAH, Peran::STAF_TU],
            'kelola-data-pribadi' => [Peran::ADMIN_SEKOLAH, Peran::STAF_TU],
            // Penilaian. Komponen dan bobot dikelola admin sekolah. Guru mata pelajaran dan wali kelas menilai hanya mata
            // pelajaran yang diampunya (dijaga di kode, bukan hanya oleh izin ini); wali kelas melihat semua mapel di kelasnya.
            'kelola-penilaian' => [Peran::ADMIN_SEKOLAH],
            'lihat-komponen-penilaian' => Peran::PANEL_SEKOLAH,
            'input-nilai' => [Peran::ADMIN_SEKOLAH, Peran::WALI_KELAS, Peran::GURU_MAPEL],
            'input-nilai-semua' => [Peran::ADMIN_SEKOLAH],
            'lihat-nilai-semua' => [Peran::ADMIN_SEKOLAH, Peran::KEPALA_SEKOLAH],
            'lihat-nilai-kelas-sendiri' => [Peran::WALI_KELAS, Peran::GURU_MAPEL],
            // Rapor. Wali kelas menyusun (catatan) dan melihat rapor kelasnya; kepala sekolah dan admin sekolah mengesahkan,
            // membuka kunci (dengan alasan), dan melihat semua. Pengesahan oleh admin dimungkinkan untuk sekolah kecil.
            'olah-rapor' => [Peran::ADMIN_SEKOLAH, Peran::WALI_KELAS],
            'sahkan-rapor' => [Peran::KEPALA_SEKOLAH, Peran::ADMIN_SEKOLAH],
            'buka-kunci-rapor' => [Peran::KEPALA_SEKOLAH, Peran::ADMIN_SEKOLAH],
            'lihat-rapor-semua' => [Peran::ADMIN_SEKOLAH, Peran::KEPALA_SEKOLAH],
            'lihat-rapor-kelas-sendiri' => [Peran::WALI_KELAS],
            // Kehadiran siswa: admin dan TU mencatat dan melihat semua kelas; wali kelas dan guru mapel hanya pada kelas atau
            // pelajaran mereka sendiri (dijaga di kode, bukan hanya oleh izin ini); kepala sekolah hanya melihat.
            'catat-kehadiran-siswa' => [Peran::ADMIN_SEKOLAH, Peran::STAF_TU, Peran::WALI_KELAS, Peran::GURU_MAPEL],
            'lihat-kehadiran-siswa-semua' => [Peran::ADMIN_SEKOLAH, Peran::KEPALA_SEKOLAH, Peran::STAF_TU],
            'kelola-kehadiran-guru' => [Peran::ADMIN_SEKOLAH, Peran::STAF_TU],
            'lihat-kehadiran-guru' => [Peran::ADMIN_SEKOLAH, Peran::KEPALA_SEKOLAH, Peran::STAF_TU],
            // Pengumuman untuk orang tua dan siswa: admin dan kepala sekolah menyusun, staf TU hanya melihat.
            'lihat-pengumuman' => [Peran::ADMIN_SEKOLAH, Peran::KEPALA_SEKOLAH, Peran::STAF_TU],
            'kelola-pengumuman' => [Peran::ADMIN_SEKOLAH, Peran::KEPALA_SEKOLAH],
            // Analisis AI (foto tangan dan wajah = data pribadi): hanya admin sekolah, dan hanya bila fitur analisis_ai dinyalakan Super Admin.
            'lihat-analisis-ai' => [Peran::ADMIN_SEKOLAH],
            'kelola-analisis-ai' => [Peran::ADMIN_SEKOLAH],
            'lihat-jadwal' => Peran::PANEL_SEKOLAH, // jadwal bukan data pribadi
            'kelola-jadwal' => [Peran::ADMIN_SEKOLAH, Peran::STAF_TU],
            // Wali kelas dan guru mata pelajaran melihat siswa di kelas yang mereka walikan atau ampu saja.
            'lihat-siswa-kelas-sendiri' => [Peran::WALI_KELAS, Peran::GURU_MAPEL],
        ];
        foreach ($izin as $nama => $peran) {
            Permission::findOrCreate($nama, 'web')->syncRoles($peran);
        }
    }
}
