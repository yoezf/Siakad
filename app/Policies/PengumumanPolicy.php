<?php

namespace App\Policies;

/** Pengumuman: admin dan kepala sekolah menyusun; staf TU hanya melihat. Tidak dihapus, hanya diarsipkan. */
class PengumumanPolicy extends SchoolDataPolicy
{
    protected string $lihat = 'lihat-pengumuman';

    protected string $kelola = 'kelola-pengumuman';
}
