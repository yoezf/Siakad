<?php

namespace App\Modules\Impor;

use RuntimeException;

/** Dilempar saat penyimpanan satu baris gagal agar seluruh transaksi impor dibatalkan. */
class BarisBermasalah extends RuntimeException
{
    public function __construct(public readonly int $baris, string $pesan)
    {
        parent::__construct($pesan);
    }
}
