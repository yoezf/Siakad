<?php

namespace App\Filament\Sekolah\Resources\TimeSlots\Pages;

use App\Filament\Sekolah\Resources\TimeSlots\TimeSlotResource;
use App\Filament\Sekolah\Support\Galat;
use App\Models\TimeSlot;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Validation\ValidationException;

class ManageTimeSlots extends ManageRecords
{
    protected static string $resource = TimeSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah jam pelajaran')->using(function (array $data, Action $action) {
                try {
                    return TimeSlot::create($data);
                } catch (ValidationException $e) {
                    Galat::beritahu($e);
                    $action->halt();
                }
            }),
        ];
    }
}
