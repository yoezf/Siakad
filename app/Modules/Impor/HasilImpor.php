<?php

namespace App\Modules\Impor;

/** Hasil impor: jumlah baris tersimpan, atau daftar kesalahan per baris (dalam hal ini tidak ada yang tersimpan). */
final class HasilImpor
{
    /** @param  list<array{baris:int, pesan:string}>  $galat */
    public function __construct(public readonly int $dibuat, public readonly array $galat = []) {}

    public static function gagal(int $baris, string $pesan): self
    {
        return new self(0, [['baris' => $baris, 'pesan' => $pesan]]);
    }

    public function berhasil(): bool
    {
        return $this->galat === [];
    }
}
