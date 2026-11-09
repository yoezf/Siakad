<?php

namespace App\Filament\Sekolah\Resources\Users\Pages;

use App\Filament\Sekolah\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;

class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah pengguna')->using(function (array $data) {
                $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
                $user->school_id = Filament::getTenant()->getKey(); // selalu sekolah aktif, tidak dari isian formulir
                $user->save();
                $user->assignRole($data['role']);

                return $user;
            }),
        ];
    }
}
