<?php

namespace App\Filament\Sekolah\Resources\TeachingAssignments\Pages;

use App\Filament\Sekolah\Resources\TeachingAssignments\TeachingAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTeachingAssignments extends ManageRecords
{
    protected static string $resource = TeachingAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah penugasan guru')];
    }
}
