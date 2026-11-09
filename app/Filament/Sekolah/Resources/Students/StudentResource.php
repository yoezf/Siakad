<?php

namespace App\Filament\Sekolah\Resources\Students;

use App\Filament\Sekolah\Resources\Students\Pages\ManageStudents;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|\UnitEnum|null $navigationGroup = 'Data master';

    protected static ?string $modelLabel = 'siswa';

    protected static ?string $pluralModelLabel = 'Siswa';

    /**
     * Pengguna tanpa izin lihat-data-pribadi (wali kelas dan guru mata pelajaran) hanya melihat siswa yang berada di
     * kelas mereka pada tahun ajaran berjalan. Ini lapisan kedua di atas StudentPolicy; tanpa kelas, hasilnya kosong.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();
        if ($user && ! $user->can('lihat-data-pribadi')) {
            $query->whereIn('students.id', Enrollment::query()->where('aktif', 1)->whereIn('classroom_id', $user->idKelasSaya() ?: [0])->select('student_id'));
        }

        return $query;
    }

    // Hak akses diatur oleh StudentPolicy.
    public static function form(Schema $schema): Schema
    {
        $dalamSekolah = fn (Unique $rule) => $rule->where('school_id', CurrentSchool::id());

        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(255),
            TextInput::make('nisn')->label('NISN')->length(10)->regex('/^\d{10}$/')->unique(ignoreRecord: true, modifyRuleUsing: $dalamSekolah)
                ->helperText('10 digit angka. Boleh dikosongkan.'),
            TextInput::make('nis')->label('NIS')->maxLength(30)->unique(ignoreRecord: true, modifyRuleUsing: $dalamSekolah),
            Select::make('gender')->label('Jenis kelamin')->options(Student::GENDER)->required(),
            TextInput::make('birth_place')->label('Tempat lahir')->maxLength(255),
            DatePicker::make('birth_date')->label('Tanggal lahir')->maxDate(now()),
            Select::make('status')->label('Status')->options(Student::STATUS)->default('aktif')->required(),
            Select::make('user_id')->label('Akun portal siswa')->searchable()->placeholder('Tanpa akun')
                ->options(fn () => User::where('school_id', CurrentSchool::id())->orderBy('name')->pluck('name', 'id'))
                ->unique(ignoreRecord: true)->helperText('Opsional. Akun dibuat di menu Pengguna dengan peran Siswa.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Kelas pada tahun ajaran berjalan, diambil dengan satu subkueri (bukan satu kueri per baris).
            ->modifyQueryUsing(function (Builder $query) {
                $tahun = CurrentSchool::id() ? AcademicYear::tahunBerjalanId(CurrentSchool::id()) : null;

                return $tahun ? $query->addSelect(['students.*', 'kelas_aktif' => Classroom::query()->select('name')
                    ->whereIn('id', Enrollment::query()->select('classroom_id')->whereColumn('student_id', 'students.id')->where('aktif', 1)->where('academic_year_id', $tahun))
                    ->limit(1)]) : $query;
            })
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('kelas_aktif')->label('Kelas')->placeholder('-'),
                TextColumn::make('nisn')->label('NISN')->searchable(),
                TextColumn::make('nis')->label('NIS')->searchable(),
                TextColumn::make('gender')->label('L/P'),
                TextColumn::make('birth_date')->label('Tanggal lahir')->date('d M Y'),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn ($state) => Student::STATUS[$state] ?? $state)
                    ->color(fn ($state) => $state === 'aktif' ? 'success' : 'gray'),
            ])
            ->filters([SelectFilter::make('status')->label('Status')->options(Student::STATUS)])
            ->defaultSort('name')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageStudents::route('/')];
    }
}
