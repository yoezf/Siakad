<?php

namespace Tests\Feature\Portal;

use App\Models\Guardian;
use App\Models\User;
use App\Support\Peran;
use Tests\Feature\Rapor\RaporTestCase;

/** Dasar uji portal. Orang tua O1 punya anak A1 (kelas 4A); orang tua O2 punya A2 dan B1; siswa A1 punya akun sendiri. */
abstract class PortalTestCase extends RaporTestCase
{
    protected $ortu1;

    protected $ortu2;

    protected $akunSiswaA1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ortu1 = $this->ortuDengan([$this->a1], 'Ortu 1');
        $this->ortu2 = $this->ortuDengan([$this->a2, $this->b1], 'Ortu 2');
        $this->akunSiswaA1 = $this->penggunaSekolah($this->s, Peran::SISWA, 'Akun A1');
        $this->untuk($this->s, fn () => $this->a1->update(['user_id' => $this->akunSiswaA1->id]));
    }

    protected function ortuDengan(array $anak, string $nama): User
    {
        $akun = $this->penggunaSekolah($this->s, Peran::ORANG_TUA, $nama);
        $this->untuk($this->s, function () use ($akun, $anak, $nama) {
            $g = Guardian::create(['name' => $nama, 'relationship' => 'ibu', 'user_id' => $akun->id]);
            $g->linkStudents(collect($anak)->pluck('id')->all());
        });

        return $akun;
    }

    protected function masukPortal(User $akun, $sekolah = null): void
    {
        $this->masukPanel($akun, 'portal', $sekolah ?? $this->s);
    }
}
