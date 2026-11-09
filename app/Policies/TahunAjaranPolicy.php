<?php

namespace App\Policies;

/** AcademicYear dan Semester: semua staf boleh melihat, hanya yang berizin boleh mengubah. */
class TahunAjaranPolicy extends SchoolDataPolicy
{
    protected string $lihat = 'lihat-tahun-ajaran';

    protected string $kelola = 'kelola-tahun-ajaran';
}
