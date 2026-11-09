<?php

namespace App\Filament\Sekolah\Pages;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class ProfilSekolah extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Profil sekolah';
    }

    public static function canView(Model $tenant): bool
    {
        return auth()->user()?->can('kelola-profil-sekolah') ?? false;
    }

    // Hanya kolom yang tampil di formulir yang bisa tersimpan; paket, fitur, dan status sekolah tidak bisa diubah dari sini.
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas sekolah')->schema([
                TextInput::make('name')->label('Nama sekolah')->required()->maxLength(255),
                CheckboxList::make('levels')->label('Jenjang')->options(config('sekolah.jenjang'))->required()->minItems(1)->columns(4),
                TextInput::make('city')->label('Kota')->maxLength(255),
                Textarea::make('address')->label('Alamat')->rows(2)->maxLength(500),
                TextInput::make('phone')->label('Telepon')->tel()->maxLength(30),
                TextInput::make('contact_email')->label('Email sekolah')->email()->maxLength(255),
            ]),
            Section::make('Pengaturan akademik')->description('Dipakai pada penilaian dan rapor. Kebijakan kurikulum ada pada sekolah.')->schema([
                TextInput::make('settings.kurikulum')->label('Kurikulum')->required()->maxLength(100)
                    ->datalist(config('sekolah.saran_kurikulum')),
                TextInput::make('settings.kkm')->label('Nilai ketuntasan (KKM)')->numeric()->required()->minValue(0)->maxValue(100)->integer(),
                Toggle::make('settings.peringkat_aktif')->label('Tampilkan peringkat siswa')
                    ->helperText('Mati secara bawaan. Peringkat hanya muncul bila sekolah memilihnya.'),
                Toggle::make('settings.absensi_guru')->label('Catat kehadiran guru dan tenaga kependidikan')
                    ->helperText('Aktif secara bawaan. Matikan bila sekolah tidak memakai absensi guru.'),
            ]),
            Section::make('Rapor')->description('Format dan tanda tangan pada rapor PDF. Rapor yang sudah disahkan tidak berubah bila pengaturan ini diganti.')->schema([
                TextInput::make('settings.rapor_judul')->label('Judul rapor')->maxLength(80),
                TextInput::make('settings.rapor_kota')->label('Kota penerbitan')->maxLength(60)->helperText('Tampil pada tanggal pengesahan, mis. "Bandung, 20 Desember 2026".'),
                TextInput::make('settings.kepala_sekolah_nama')->label('Nama kepala sekolah')->maxLength(100),
                TextInput::make('settings.kepala_sekolah_nip')->label('NIP kepala sekolah')->maxLength(30),
                Toggle::make('settings.rapor_tampil_kehadiran')->label('Tampilkan kehadiran pada rapor'),
            ]),
        ]);
    }

    // Isi bawaan ditampilkan pada formulir agar admin melihat nilai yang sedang berlaku.
    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (config('sekolah.pengaturan') as $k => $bawaan) {
            $data['settings'][$k] ??= $bawaan;
        }
        $data['levels'] ??= ['sd'];

        return $data;
    }
}
