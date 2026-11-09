<?php

namespace App\Filament\Sekolah\Resources\AssessmentComponents\Pages;

use App\Filament\Sekolah\Resources\AssessmentComponents\AssessmentComponentResource;
use App\Filament\Sekolah\Support\Galat;
use App\Models\AssessmentComponent;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Validation\ValidationException;

class ManageAssessmentComponents extends ManageRecords
{
    protected static string $resource = AssessmentComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah komponen')->using(function (array $data, Action $action) {
                try {
                    return AssessmentComponent::create($data);
                } catch (ValidationException $e) {
                    Galat::beritahu($e);
                    $action->halt();
                }
            }),
        ];
    }
}
