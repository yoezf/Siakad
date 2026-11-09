<?php

namespace App\Filament\Sekolah\Resources\Classrooms;

use App\Filament\Sekolah\Resources\Classrooms\Pages\ManageClassrooms;
use App\Models\Classroom;
use App\Models\Staff;
use App\Support\Tenancy\CurrentSchool;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class ClassroomResource extends Resource
{
    protected static ?string $model = Classroom::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Data master';

    protected static ?string $modelLabel = 'kelas';

    protected static ?string $pluralModelLabel = 'Kelas';

    // Hak akses diatur oleh DataMasterPolicy. Siswa ditempatkan lewat menu Pembagian kelas.
    // Parameter closure Filament harus bernama sesuai yang dikenalinya ($rule, $query, $record, $get): diisi menurut nama.
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('academic_year_id')->label('Tahun ajaran')->relationship('academicYear', 'name')->required()->live()
                ->disabledOn('edit')->helperText('Tahun ajaran sebuah kelas tidak bisa diubah setelah dibuat.'),
            TextInput::make('name')->label('Nama kelas')->placeholder('4A')->required()->maxLength(30)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('school_id', CurrentSchool::id())->where('academic_year_id', $get('academic_year_id')))
                ->validationMessages(['unique' => 'Nama kelas ini sudah ada pada tahun ajaran tersebut.']),
            Select::make('grade')->label('Tingkat')->options(array_combine(range(1, 12), range(1, 12)))->required()
                ->helperText('Tingkat 1 sampai 12 (SD 1-6, SMP 7-9, SMA 10-12).'),
            Select::make('homeroom_staff_id')->label('Wali kelas')->searchable()->placeholder('Belum ditunjuk')
                ->options(fn () => Staff::where('kind', 'guru')->where('status', 'aktif')->orderBy('name')->pluck('name', 'id'))
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('academic_year_id', $get('academic_year_id')))
                ->validationMessages(['unique' => 'Guru ini sudah menjadi wali kelas lain pada tahun ajaran tersebut.']),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('homeroom')->withCount(['enrollments as siswa_count' => fn ($e) => $e->where('aktif', 1)]))
            ->columns([
                TextColumn::make('academicYear.name')->label('Tahun ajaran')->sortable(),
                TextColumn::make('name')->label('Kelas')->searchable()->sortable(),
                TextColumn::make('grade')->label('Tingkat')->sortable(),
                TextColumn::make('homeroom.name')->label('Wali kelas')->placeholder('-'),
                TextColumn::make('siswa_count')->label('Siswa'),
            ])
            ->filters([SelectFilter::make('academic_year_id')->label('Tahun ajaran')->relationship('academicYear', 'name')])
            ->defaultSort('grade')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageClassrooms::route('/')];
    }
}
