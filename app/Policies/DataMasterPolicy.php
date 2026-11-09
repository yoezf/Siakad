<?php

namespace App\Policies;

/** Mata pelajaran dan kelas: bukan data pribadi, jadi semua staf boleh melihat. */
class DataMasterPolicy extends SchoolDataPolicy
{
    protected string $lihat = 'lihat-data-master';

    protected string $kelola = 'kelola-data-master';
}
