<?php

namespace App\Filament\Sekolah\Resources\TeachingAssignments;

use App\Filament\Sekolah\Resources\TeachingAssignments\Pages\ManageTeachingAssignments;
use App\Models\Staff;
use App\Models\TeachingAssignment;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class TeachingAssignmentResource extends Resource
{
    protected static ?string $model = TeachingAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?string $modelLabel = 'penugasan guru';

    protected static ?string $pluralModelLabel = 'Penugasan guru';

    protected static ?string $slug = 'penugasan-guru';

    // Hak akses diatur oleh DataMasterPolicy. Penugasan tidak dihapus; yang boleh berganti hanya gurunya.
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('classroom_id')->label('Kelas')->required()->live()->disabledOn('edit')
                ->relationship('classroom', 'name', fn (Builder $query) => $query->with('academicYear'))
                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} ({$record->academicYear->name})"),
            Select::make('subject_id')->label('Mata pelajaran')->required()->disabledOn('edit')
                ->relationship('subject', 'name')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('classroom_id', $get('classroom_id')))
                ->validationMessages(['unique' => 'Mata pelajaran ini sudah punya guru pada kelas tersebut. Ganti gurunya lewat tombol Ubah.']),
            Select::make('staff_id')->label('Guru pengampu')->required()->searchable()
                ->options(fn () => Staff::where('kind', 'guru')->where('status', 'aktif')->orderBy('name')->pluck('name', 'id')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['classroom.academicYear', 'subject', 'staff']))
            ->columns([
                TextColumn::make('classroom.name')->label('Kelas')->sortable(),
                TextColumn::make('classroom.academicYear.name')->label('Tahun ajaran'),
                TextColumn::make('subject.name')->label('Mata pelajaran')->sortable(),
                TextColumn::make('staff.name')->label('Guru pengampu')->sortable(),
            ])
            ->filters([
                SelectFilter::make('classroom_id')->label('Kelas')->relationship('classroom', 'name'),
                SelectFilter::make('staff_id')->label('Guru')->relationship('staff', 'name'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTeachingAssignments::route('/')];
    }
}
