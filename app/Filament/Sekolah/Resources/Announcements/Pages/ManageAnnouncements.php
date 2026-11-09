<?php

namespace App\Filament\Sekolah\Resources\Announcements\Pages;

use App\Filament\Sekolah\Resources\Announcements\AnnouncementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAnnouncements extends ManageRecords
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Buat pengumuman')];
    }
}
