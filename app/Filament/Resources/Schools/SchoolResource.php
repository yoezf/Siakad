<?php

namespace App\Filament\Resources\Schools;

use App\Filament\Resources\Schools\Pages\ManageSchools;
use App\Models\School;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SchoolResource extends Resource
{
    protected static ?string $model = School::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $modelLabel = 'sekolah';

    protected static ?string $pluralModelLabel = 'Daftar sekolah';

    protected static ?string $slug = 'sekolah';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('kelola-sekolah') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        $paket = collect(config('fitur.paket'))->map(fn ($p) => $p['label'])->all();

        return $schema->components([
            TextInput::make('name')->label('Nama sekolah')->required()->maxLength(255),
            TextInput::make('city')->label('Kota')->maxLength(255),
            Select::make('plan')->label('Paket')->options($paket)->default('dasar')->required()->live()
                ->afterStateUpdated(fn ($state, Set $set) => $set('features', config("fitur.paket.{$state}.fitur", []))),
            CheckboxList::make('features')->label('Fitur aktif (di luar fitur akademik inti)')->options(config('fitur.fitur'))
                ->default([])->columns(2),
            Select::make('status')->label('Status')->options(['aktif' => 'Aktif', 'uji_coba' => 'Uji coba', 'nonaktif' => 'Nonaktif'])
                ->default('aktif')->required(),
            // Akun admin pertama hanya dibuat saat sekolah didaftarkan.
            TextInput::make('admin_name')->label('Nama admin sekolah')->required()->visibleOn('create')->dehydrated(),
            TextInput::make('admin_email')->label('Email admin sekolah')->email()->required()->visibleOn('create')
                ->unique('users', 'email')->dehydrated(),
            TextInput::make('admin_password')->label('Kata sandi awal')->password()->revealable()->minLength(12)->required()
                ->visibleOn('create')->dehydrated(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Sekolah')->searchable()->sortable(),
                TextColumn::make('city')->label('Kota'),
                TextColumn::make('plan')->label('Paket')->badge()->formatStateUsing(fn ($state) => config("fitur.paket.{$state}.label", $state)),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn ($state) => match ($state) { 'aktif' => 'success', 'uji_coba' => 'warning', default => 'gray' }),
                TextColumn::make('users_count')->label('Pengguna')->counts('users'),
            ])
            // Sekolah tidak dihapus: nonaktifkan lewat status agar riwayat tetap utuh.
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSchools::route('/')];
    }
}
