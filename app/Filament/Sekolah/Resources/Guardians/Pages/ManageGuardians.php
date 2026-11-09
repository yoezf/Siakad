<?php

namespace App\Filament\Sekolah\Resources\Guardians\Pages;

use App\Filament\Sekolah\Resources\Guardians\GuardianResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGuardians extends ManageRecords
{
    protected static string $resource = GuardianResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah orang tua atau wali')];
    }
}
