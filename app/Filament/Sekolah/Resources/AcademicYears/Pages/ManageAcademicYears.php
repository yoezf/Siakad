<?php

namespace App\Filament\Sekolah\Resources\AcademicYears\Pages;

use App\Filament\Sekolah\Resources\AcademicYears\AcademicYearResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAcademicYears extends ManageRecords
{
    protected static string $resource = AcademicYearResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah tahun ajaran')];
    }
}
