<?php

namespace App\Filament\Sekolah\Pages;

use Filament\Pages\Dashboard;

/** Beranda panel Sekolah. Sama seperti Dashboard bawaan, tetapi grid enam kolom agar kartu bisa disusun berdampingan. */
class Dasbor extends Dashboard
{
    public function getColumns(): int|array
    {
        return ['default' => 1, 'md' => 2, 'xl' => 6];
    }
}
