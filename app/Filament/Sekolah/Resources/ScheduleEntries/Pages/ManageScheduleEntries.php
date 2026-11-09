<?php

namespace App\Filament\Sekolah\Resources\ScheduleEntries\Pages;

use App\Filament\Sekolah\Resources\ScheduleEntries\ScheduleEntryResource;
use App\Filament\Sekolah\Support\Galat;
use App\Models\ScheduleEntry;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Validation\ValidationException;

class ManageScheduleEntries extends ManageRecords
{
    protected static string $resource = ScheduleEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah pelajaran ke jadwal')->using(function (array $data, Action $action) {
                try {
                    return ScheduleEntry::create($data);
                } catch (ValidationException $e) {
                    Galat::beritahu($e, 'Jadwal bentrok atau tidak sah');
                    $action->halt();
                }
            }),
        ];
    }
}
