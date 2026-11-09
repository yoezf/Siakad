<?php

namespace App\Filament\Sekolah\Resources\ScheduleEntries;

use App\Filament\Sekolah\Resources\ScheduleEntries\Pages\ManageScheduleEntries;
use App\Filament\Sekolah\Support\Galat;
use App\Models\ScheduleEntry;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\TimeSlot;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ScheduleEntryResource extends Resource
{
    protected static ?string $model = ScheduleEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Jadwal';

    protected static ?string $modelLabel = 'pelajaran';

    protected static ?string $pluralModelLabel = 'Jadwal pelajaran';

    // Hak akses diatur oleh JadwalPolicy. Parameter closure Filament harus bernama $query, $record, $action, $get, dst.
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('classroom_id')->label('Kelas')->required()->live()->disabledOn('edit')
                ->relationship('classroom', 'name', fn (Builder $query) => $query->with('academicYear'))
                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} ({$record->academicYear->name})"),
            Select::make('subject_id')->label('Mata pelajaran')->required()
                ->options(fn (Get $get) => $get('classroom_id')
                    ? Subject::whereIn('id', TeachingAssignment::where('classroom_id', $get('classroom_id'))->select('subject_id'))->orderBy('name')->pluck('name', 'id')
                    : [])
                ->helperText('Hanya mata pelajaran yang sudah punya guru pengampu di kelas ini. Gurunya diambil dari Penugasan guru.'),
            Select::make('day')->label('Hari')->options(ScheduleEntry::HARI)->required(),
            Select::make('time_slot_id')->label('Jam pelajaran')->required()
                ->options(fn () => TimeSlot::where('is_break', false)->orderBy('number')->get()->mapWithKeys(fn ($jam) => [$jam->id => $jam->label()])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['classroom', 'subject', 'staff', 'timeSlot'])
                ->orderBy('day')->orderBy(TimeSlot::query()->select('number')->whereColumn('time_slots.id', 'schedule_entries.time_slot_id')))
            ->columns([
                TextColumn::make('classroom.name')->label('Kelas'),
                TextColumn::make('day')->label('Hari')->formatStateUsing(fn ($state) => ScheduleEntry::HARI[$state] ?? $state),
                TextColumn::make('timeSlot.name')->label('Jam')->description(fn (ScheduleEntry $record) => $record->timeSlot->rentang()),
                TextColumn::make('subject.name')->label('Mata pelajaran'),
                TextColumn::make('staff.name')->label('Guru'),
            ])
            ->filters([
                SelectFilter::make('classroom_id')->label('Kelas')->relationship('classroom', 'name'),
                SelectFilter::make('staff_id')->label('Guru')->relationship('staff', 'name'),
                SelectFilter::make('subject_id')->label('Mata pelajaran')->relationship('subject', 'name'),
                SelectFilter::make('day')->label('Hari')->options(ScheduleEntry::HARI),
            ])
            ->recordActions([
                EditAction::make()->using(function (Model $record, array $data, Action $action) {
                    try {
                        $record->update($data);

                        return $record;
                    } catch (ValidationException $e) {
                        Galat::beritahu($e, 'Jadwal tidak bisa diubah');
                        $action->halt();
                    }
                }),
                DeleteAction::make()->before(function (ScheduleEntry $record, Action $action) {
                    // Data kehadiran mengacu ke pelajaran ini (basis data juga menolak penghapusannya).
                    if ($record->attendances()->exists()) {
                        Notification::make()->danger()->persistent()->title('Pelajaran ini sudah punya data kehadiran.')
                            ->body('Jadwal yang sudah dipakai mencatat kehadiran tidak bisa dihapus.')->send();
                        $action->halt();
                    }
                }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageScheduleEntries::route('/')];
    }
}
