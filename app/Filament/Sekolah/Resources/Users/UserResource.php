<?php

namespace App\Filament\Sekolah\Resources\Users;

use App\Filament\Sekolah\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Support\Peran;
use App\Support\Tenancy\CurrentSchool;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'pengguna';

    protected static ?string $pluralModelLabel = 'Pengguna';

    protected static ?string $slug = 'pengguna';

    /** Lapisan kedua di atas penyaringan tenant Filament; tanpa sekolah aktif hasilnya kosong, bukan semua pengguna. */
    public static function getEloquentQuery(): Builder
    {
        $id = CurrentSchool::id();

        return parent::getEloquentQuery()->when(
            $id === null,
            fn (Builder $q) => $q->whereRaw('1 = 0'),
            fn (Builder $q) => $q->where('users.school_id', $id),
        );
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('kelola-pengguna') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(255),
            TextInput::make('email')->label('Email')->email()->required()->unique('users', 'email', ignoreRecord: true),
            // Hanya peran sekolah yang bisa dipilih; super_admin tidak pernah ada di daftar ini.
            Select::make('role')->label('Peran')->options(Peran::SEKOLAH)->required(),
            TextInput::make('password')->label('Kata sandi')->password()->revealable()->minLength(12)
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn ($state) => filled($state)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Nama')->searchable()->sortable(),
            TextColumn::make('email')->label('Email')->searchable(),
            TextColumn::make('roles.name')->label('Peran')->badge()->formatStateUsing(fn ($state) => Peran::SEKOLAH[$state] ?? $state),
        ])
            // Pengguna tidak dihapus agar riwayat audit tetap utuh; penonaktifan akun menyusul bersama modul data master.
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, User $record) => $data + ['role' => $record->roles->first()?->name])
                    ->using(function (User $record, array $data) {
                        $role = $data['role'];
                        unset($data['role']);
                        $record->update($data);
                        $record->syncRoles([$role]);

                        return $record;
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
