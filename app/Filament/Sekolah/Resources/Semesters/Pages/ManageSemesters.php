<?php

namespace App\Filament\Sekolah\Resources\Semesters\Pages;

use App\Filament\Sekolah\Resources\Semesters\SemesterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSemesters extends ManageRecords
{
    protected static string $resource = SemesterResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah semester')];
    }
}
