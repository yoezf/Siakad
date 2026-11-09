<?php

namespace App\Filament\Sekolah\Resources\Staff\Pages;

use App\Filament\Sekolah\Resources\Staff\StaffResource;
use App\Filament\Sekolah\Support\AksiImpor;
use App\Models\Staff;
use App\Modules\Impor\ImporStaf;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStaff extends ManageRecords
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...AksiImpor::buat(new ImporStaf, Staff::class, 'guru-dan-tendik'),
            CreateAction::make()->label('Tambah guru atau tendik'),
        ];
    }
}
