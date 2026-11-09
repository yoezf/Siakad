<?php

namespace App\Filament\Sekolah\Resources\Announcements;

use App\Filament\Sekolah\Resources\Announcements\Pages\ManageAnnouncements;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Classroom;
use App\Support\Tenancy\CurrentSchool;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|\UnitEnum|null $navigationGroup = 'Komunikasi';

    protected static ?string $modelLabel = 'pengumuman';

    protected static ?string $pluralModelLabel = 'Pengumuman';

    protected static ?string $navigationLabel = 'Pengumuman';

    protected static ?string $slug = 'pengumuman';

    // Hak akses diatur oleh PengumumanPolicy.
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Judul')->required()->minLength(3)->maxLength(150),
            Textarea::make('body')->label('Isi pengumuman')->required()->rows(6)->maxLength(5000),
            Select::make('audience')->label('Dibaca oleh')->options(Announcement::AUDIENCE)->default('semua')->required()->live(),
            Select::make('classroom_id')->label('Kelas')->visible(fn ($get) => $get('audience') === 'kelas')->required(fn ($get) => $get('audience') === 'kelas')
                ->options(fn () => Classroom::where('academic_year_id', AcademicYear::tahunBerjalanId(CurrentSchool::id() ?? 0) ?? 0)->orderBy('grade')->orderBy('name')->pluck('name', 'id')),
            Select::make('status')->label('Status')->options(Announcement::STATUS)->default('draf')->required()
                ->helperText('Hanya pengumuman berstatus Terbit yang terlihat di portal. Pengumuman terbit tidak bisa kembali ke draf; arsipkan bila tidak berlaku lagi.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Judul')->searchable()->limit(60),
                TextColumn::make('audience')->label('Dibaca oleh')->formatStateUsing(fn ($state, Announcement $record) => $state === 'kelas'
                    ? 'Kelas '.($record->classroom?->name ?? '-') : (Announcement::AUDIENCE[$state] ?? $state)),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn ($state) => Announcement::STATUS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {'terbit' => 'success', 'draf' => 'warning', default => 'gray'}),
                TextColumn::make('published_at')->label('Terbit')->dateTime('d M Y H:i')->placeholder('-'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([SelectFilter::make('status')->label('Status')->options(Announcement::STATUS)])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAnnouncements::route('/')];
    }
}
