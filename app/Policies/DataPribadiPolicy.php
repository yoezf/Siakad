<?php

namespace App\Policies;

/** Siswa, guru dan tenaga kependidikan, orang tua: data pribadi, hanya untuk peran yang memang perlu (akses minimum). */
class DataPribadiPolicy extends SchoolDataPolicy
{
    protected string $lihat = 'lihat-data-pribadi';

    protected string $kelola = 'kelola-data-pribadi';
}
