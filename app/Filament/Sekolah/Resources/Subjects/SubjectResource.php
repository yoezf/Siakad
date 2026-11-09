<?php

namespace App\Filament\Sekolah\Resources\Subjects;

use App\Filament\Sekolah\Resources\Subjects\Pages\ManageSubjects;
use App\Models\Subject;
use App\Support\Tenancy\CurrentSchool;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class SubjectResource extends Resource
{
    protected static ?string $model = Subject::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|\UnitEnum|null $navigationGroup = 'Data master';

    protected static ?string $modelLabel = 'mata pelajaran';

    protected static ?string $pluralModelLabel = 'Mata pelajaran';

    protected static ?string $slug = 'mata-pelajaran';

    // Hak akses diatur oleh DataMasterPolicy.
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // Parameter closure harus bernama $rule: Filament mengisi parameter menurut namanya.
            TextInput::make('code')->label('Kode')->required()->maxLength(20)->placeholder('MTK')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('school_id', CurrentSchool::id())),
            TextInput::make('name')->label('Nama mata pelajaran')->required()->maxLength(255),
            TextInput::make('kkm')->label('KKM khusus (opsional)')->numeric()->integer()->minValue(0)->maxValue(100)
                ->helperText('Kosongkan untuk memakai KKM sekolah (Profil sekolah).'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable(),
                TextColumn::make('name')->label('Mata pelajaran')->searchable()->sortable(),
                TextColumn::make('kkm')->label('KKM')->placeholder('ikut sekolah'),
            ])
            ->defaultSort('code')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSubjects::route('/')];
    }
}
