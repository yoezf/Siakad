<?php

namespace App\Filament\Sekolah\Resources\Students\Pages;

use App\Filament\Sekolah\Resources\Students\StudentResource;
use App\Filament\Sekolah\Support\AksiImpor;
use App\Models\Student;
use App\Modules\Impor\ImporSiswa;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStudents extends ManageRecords
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...AksiImpor::buat(new ImporSiswa, Student::class, 'siswa'),
            CreateAction::make()->label('Tambah siswa'),
        ];
    }
}
