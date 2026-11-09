<?php

namespace App\Filament\Sekolah\Resources\AcademicYears;

use App\Filament\Sekolah\Resources\AcademicYears\Pages\ManageAcademicYears;
use App\Models\AcademicYear;
use App\Support\Tenancy\CurrentSchool;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class AcademicYearResource extends Resource
{
    protected static ?string $model = AcademicYear::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?string $modelLabel = 'tahun ajaran';

    protected static ?string $pluralModelLabel = 'Tahun ajaran';

    // Hak akses diatur oleh TahunAjaranPolicy.
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Tahun ajaran')->placeholder('2025/2026')->required()->maxLength(20)
                ->rules([fn (): Closure => function (string $attribute, $value, Closure $fail) {
                    // Format AAAA/BBBB dengan BBBB = AAAA + 1.
                    if (! preg_match('#^(\d{4})/(\d{4})$#', (string) $value, $m) || (int) $m[2] !== (int) $m[1] + 1) {
                        $fail('Gunakan format 2025/2026 (tahun kedua tepat satu tahun setelah tahun pertama).');
                    }
                }])
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('school_id', CurrentSchool::id())),
            DatePicker::make('start_date')->label('Tanggal mulai')->required(),
            DatePicker::make('end_date')->label('Tanggal selesai')->required()->after('start_date'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Tahun ajaran')->sortable()->searchable(),
                TextColumn::make('start_date')->label('Mulai')->date('d M Y'),
                TextColumn::make('end_date')->label('Selesai')->date('d M Y'),
                TextColumn::make('semesters_count')->label('Semester')->counts('semesters'),
                TextColumn::make('status')->label('Status')->badge()->color(fn ($state) => $state === 'Aktif' ? 'success' : 'gray')
                    ->state(fn (AcademicYear $r) => $r->semesters()->where('aktif', 1)->exists() ? 'Aktif' : '-'),
            ])
            ->defaultSort('start_date', 'desc')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAcademicYears::route('/')];
    }
}
