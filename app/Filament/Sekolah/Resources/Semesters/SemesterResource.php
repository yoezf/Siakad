<?php

namespace App\Filament\Sekolah\Resources\Semesters;

use App\Filament\Sekolah\Resources\Semesters\Pages\ManageSemesters;
use App\Models\Semester;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class SemesterResource extends Resource
{
    protected static ?string $model = Semester::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?string $modelLabel = 'semester';

    protected static ?string $pluralModelLabel = 'Semester';

    // Hak akses diatur oleh TahunAjaranPolicy.
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('academic_year_id')->label('Tahun ajaran')->relationship('academicYear', 'name')->required()->live(),
            Select::make('number')->label('Semester')->options([1 => 'Ganjil', 2 => 'Genap'])->required()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('academic_year_id', $get('academic_year_id')))
                ->validationMessages(['unique' => 'Semester ini sudah ada pada tahun ajaran tersebut.']),
            DatePicker::make('start_date')->label('Tanggal mulai')->required(),
            DatePicker::make('end_date')->label('Tanggal selesai')->required()->after('start_date'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('academicYear.name')->label('Tahun ajaran')->sortable(),
                TextColumn::make('label')->label('Semester'),
                TextColumn::make('start_date')->label('Mulai')->date('d M Y'),
                TextColumn::make('end_date')->label('Selesai')->date('d M Y'),
                TextColumn::make('status')->label('Status')->badge()->color(fn ($state) => $state === 'Aktif' ? 'success' : 'gray')
                    ->state(fn (Semester $r) => $r->isActive() ? 'Aktif' : '-'),
            ])
            ->defaultSort('start_date', 'desc')
            ->recordActions([
                Action::make('activate')->label('Jadikan aktif')->icon(Heroicon::OutlinedCheckCircle)->requiresConfirmation()
                    ->modalDescription('Semester lain di sekolah ini akan dinonaktifkan. Hanya satu semester yang boleh aktif.')
                    ->authorize('update')
                    ->visible(fn (Semester $r) => ! $r->isActive())
                    ->action(fn (Semester $r) => $r->activate()),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSemesters::route('/')];
    }
}
