<?php

namespace App\Filament\Sekolah\Resources\Classrooms\Pages;

use App\Filament\Sekolah\Resources\Classrooms\ClassroomResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageClassrooms extends ManageRecords
{
    protected static string $resource = ClassroomResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah kelas')];
    }
}
