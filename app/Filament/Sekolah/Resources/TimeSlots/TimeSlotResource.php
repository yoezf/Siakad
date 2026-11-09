<?php

namespace App\Filament\Sekolah\Resources\TimeSlots;

use App\Filament\Sekolah\Resources\TimeSlots\Pages\ManageTimeSlots;
use App\Filament\Sekolah\Support\Galat;
use App\Models\TimeSlot;
use App\Support\Tenancy\CurrentSchool;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;

class TimeSlotResource extends Resource
{
    protected static ?string $model = TimeSlot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'Jadwal';

    protected static ?string $modelLabel = 'jam pelajaran';

    protected static ?string $pluralModelLabel = 'Jam pelajaran';

    protected static ?string $navigationLabel = 'Jam pelajaran';

    protected static ?string $slug = 'jam-pelajaran';

    // Hak akses diatur oleh JadwalPolicy. Parameter closure Filament harus bernama $rule, $record, $action, dst.
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('number')->label('Urutan')->numeric()->integer()->minValue(1)->maxValue(30)->required()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('school_id', CurrentSchool::id()))
                ->helperText('Jam ke-1, ke-2, dan seterusnya. Menentukan urutan di jadwal.'),
            TextInput::make('name')->label('Nama')->placeholder('Jam ke-1')->required()->maxLength(40),
            TimePicker::make('start_time')->label('Mulai')->seconds(false)->required(),
            TimePicker::make('end_time')->label('Selesai')->seconds(false)->required()->after('start_time'),
            Toggle::make('is_break')->label('Waktu istirahat')->helperText('Istirahat tidak bisa diisi pelajaran.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('Urutan')->sortable(),
                TextColumn::make('name')->label('Nama'),
                TextColumn::make('rentang')->label('Jam')->state(fn (TimeSlot $record) => $record->rentang()),
                IconColumn::make('is_break')->label('Istirahat')->boolean(),
                TextColumn::make('entries_count')->label('Dipakai')->counts('entries'),
            ])
            ->defaultSort('number')
            ->recordActions([
                EditAction::make()->using(function (Model $record, array $data, Action $action) {
                    try {
                        $record->update($data);

                        return $record;
                    } catch (ValidationException $e) {
                        Galat::beritahu($e);
                        $action->halt();
                    }
                }),
                DeleteAction::make()->before(function (TimeSlot $record, Action $action) {
                    if ($record->entries()->exists()) {
                        Notification::make()->danger()->persistent()->title('Jam pelajaran ini masih dipakai jadwal.')
                            ->body('Pindahkan atau hapus dulu pelajaran yang memakainya.')->send();
                        $action->halt();
                    }
                }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTimeSlots::route('/')];
    }
}
