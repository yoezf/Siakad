<?php

namespace App\Filament\Sekolah\Resources\Enrollments;

use App\Filament\Sekolah\Resources\Enrollments\Pages\ManageEnrollments;
use App\Models\Classroom;
use App\Models\Enrollment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class EnrollmentResource extends Resource
{
    protected static ?string $model = Enrollment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?string $modelLabel = 'penempatan siswa';

    protected static ?string $pluralModelLabel = 'Pembagian kelas';

    // Hak akses diatur oleh DataPribadiPolicy. Penempatan tidak diedit atau dihapus: pindahkan agar riwayat utuh.
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['student', 'classroom', 'academicYear']))
            ->columns([
                TextColumn::make('student.name')->label('Siswa')->searchable()->sortable(),
                TextColumn::make('classroom.name')->label('Kelas')->sortable(),
                TextColumn::make('academicYear.name')->label('Tahun ajaran'),
                TextColumn::make('joined_at')->label('Masuk')->date('d M Y'),
                TextColumn::make('left_at')->label('Keluar')->date('d M Y')->placeholder('-'),
                TextColumn::make('status')->label('Status')->badge()->color(fn ($state) => $state === 'Berlaku' ? 'success' : 'gray')
                    ->state(fn (Enrollment $r) => $r->isActive() ? 'Berlaku' : 'Ditutup'),
                TextColumn::make('note')->label('Catatan')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('berlaku')->label('Hanya yang berlaku')->default()->query(fn (Builder $query) => $query->where('aktif', 1)),
                SelectFilter::make('academic_year_id')->label('Tahun ajaran')->relationship('academicYear', 'name'),
                SelectFilter::make('classroom_id')->label('Kelas')->relationship('classroom', 'name'),
            ])
            ->defaultSort('joined_at', 'desc')
            ->recordActions([
                Action::make('pindahkan')->label('Pindahkan')->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->authorize('update')
                    ->visible(fn (Enrollment $r) => $r->isActive())
                    ->modalDescription('Penempatan lama ditutup dan penempatan baru dibuka. Riwayat tetap tersimpan.')
                    ->schema(fn (Enrollment $r) => [
                        Select::make('classroom_id')->label('Pindah ke kelas')->required()
                            ->options(fn () => Classroom::where('academic_year_id', $r->academic_year_id)->where('id', '!=', $r->classroom_id)->orderBy('name')->pluck('name', 'id')),
                        TextInput::make('note')->label('Catatan')->maxLength(255),
                    ])
                    ->action(function (Enrollment $r, array $data) {
                        try {
                            $r->pindahkan(Classroom::findOrFail($data['classroom_id']), $data['note'] ?? null);
                            Notification::make()->success()->title('Siswa dipindahkan.')->send();
                        } catch (ValidationException $e) {
                            Notification::make()->danger()->title(collect($e->errors())->flatten()->implode(' '))->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageEnrollments::route('/')];
    }
}
