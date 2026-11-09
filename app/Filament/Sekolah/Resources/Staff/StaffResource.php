<?php

namespace App\Filament\Sekolah\Resources\Staff;

use App\Filament\Sekolah\Resources\Staff\Pages\ManageStaff;
use App\Models\Staff;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class StaffResource extends Resource
{
    protected static ?string $model = Staff::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup = 'Data master';

    protected static ?string $modelLabel = 'guru atau tenaga kependidikan';

    protected static ?string $pluralModelLabel = 'Guru dan tendik';

    protected static ?string $navigationLabel = 'Guru dan tendik';

    protected static ?string $slug = 'guru-staf';

    // Hak akses diatur oleh DataPribadiPolicy.
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(255),
            // Parameter closure harus bernama $rule: Filament mengisi parameter menurut namanya.
            TextInput::make('nip')->label('NIP')->maxLength(30)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('school_id', CurrentSchool::id())),
            Select::make('kind')->label('Jenis')->options(Staff::KIND)->default('guru')->required(),
            TextInput::make('phone')->label('Telepon')->tel()->maxLength(30),
            TextInput::make('email')->label('Email')->email()->maxLength(255),
            Select::make('status')->label('Status')->options(Staff::STATUS)->default('aktif')->required(),
            // Akun masuk bersifat opsional dan hanya dari pengguna sekolah ini.
            Select::make('user_id')->label('Akun masuk')->searchable()->placeholder('Tanpa akun')
                ->options(fn () => User::where('school_id', CurrentSchool::id())->orderBy('name')->pluck('name', 'id'))
                ->unique(ignoreRecord: true)->helperText('Pengguna dibuat di menu Pengguna.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('nip')->label('NIP')->searchable(),
                TextColumn::make('kind')->label('Jenis')->badge()->formatStateUsing(fn ($state) => Staff::KIND[$state] ?? $state),
                TextColumn::make('phone')->label('Telepon'),
                TextColumn::make('user.email')->label('Akun')->placeholder('-'),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn ($state) => Staff::STATUS[$state] ?? $state)
                    ->color(fn ($state) => $state === 'aktif' ? 'success' : 'gray'),
            ])
            ->filters([SelectFilter::make('kind')->label('Jenis')->options(Staff::KIND)])
            ->defaultSort('name')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageStaff::route('/')];
    }
}
