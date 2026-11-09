<?php

namespace App\Filament\Sekolah\Resources\Guardians;

use App\Filament\Sekolah\Resources\Guardians\Pages\ManageGuardians;
use App\Models\Guardian;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GuardianResource extends Resource
{
    protected static ?string $model = Guardian::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Data master';

    protected static ?string $modelLabel = 'orang tua atau wali';

    protected static ?string $pluralModelLabel = 'Orang tua dan wali';

    protected static ?string $slug = 'orang-tua';

    // Hak akses diatur oleh DataPribadiPolicy.
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(255),
            Select::make('relationship')->label('Hubungan')->options(Guardian::RELATIONSHIP)->default('wali')->required(),
            TextInput::make('phone')->label('Telepon')->tel()->maxLength(30),
            TextInput::make('email')->label('Email')->email()->maxLength(255),
            // Satu orang tua boleh punya beberapa anak. Hanya siswa sekolah ini yang bisa dipilih dan disimpan.
            Select::make('students')->label('Anak')->multiple()->searchable()->preload()
                ->relationship('students', 'name')
                ->saveRelationshipsUsing(fn (Guardian $record, $state) => $record->linkStudents($state ?? [])),
            Select::make('user_id')->label('Akun portal')->searchable()->placeholder('Tanpa akun')
                ->options(fn () => User::where('school_id', CurrentSchool::id())->orderBy('name')->pluck('name', 'id'))
                ->unique(ignoreRecord: true)->helperText('Akun dibuat di menu Pengguna dengan peran Orang tua.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('relationship')->label('Hubungan')->badge()->formatStateUsing(fn ($state) => Guardian::RELATIONSHIP[$state] ?? $state),
                TextColumn::make('students.name')->label('Anak')->badge()->limitList(3),
                TextColumn::make('phone')->label('Telepon'),
                TextColumn::make('user.email')->label('Akun')->placeholder('-'),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageGuardians::route('/')];
    }
}
