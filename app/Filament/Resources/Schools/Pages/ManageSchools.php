<?php

namespace App\Filament\Resources\Schools\Pages;

use App\Filament\Resources\Schools\SchoolResource;
use App\Modules\Platform\RegisterSchool;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSchools extends ManageRecords
{
    protected static string $resource = SchoolResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Daftarkan sekolah')
                ->using(fn (array $data) => app(RegisterSchool::class)->handle($data)),
        ];
    }
}
