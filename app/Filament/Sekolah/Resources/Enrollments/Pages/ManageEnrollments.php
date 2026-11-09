<?php

namespace App\Filament\Sekolah\Resources\Enrollments\Pages;

use App\Filament\Sekolah\Resources\Enrollments\EnrollmentResource;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageEnrollments extends ManageRecords
{
    protected static string $resource = EnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('tempatkan')->label('Tempatkan siswa ke kelas')
                ->authorize(fn () => auth()->user()?->can('create', Enrollment::class) ?? false)
                ->modalDescription('Hanya siswa aktif yang belum punya kelas pada tahun ajaran kelas tersebut yang bisa dipilih. Jika satu siswa bermasalah, tidak ada yang ditempatkan.')
                ->schema([
                    Select::make('classroom_id')->label('Kelas')->required()->live()
                        ->options(fn () => Classroom::with('academicYear')->get()->sortBy('name')->mapWithKeys(fn ($c) => [$c->id => "{$c->name} ({$c->academicYear->name})"])),
                    Select::make('student_ids')->label('Siswa')->multiple()->required()->searchable()
                        ->getSearchResultsUsing(fn (string $cari, Get $get) => $this->siswaBelumPunyaKelas($get('classroom_id'))
                            ->where(fn ($q) => $q->where('name', 'like', "%{$cari}%")->orWhere('nis', 'like', "%{$cari}%"))->orderBy('name')->limit(50)->pluck('name', 'id'))
                        ->getOptionLabelsUsing(fn (array $values) => Student::whereIn('id', $values)->pluck('name', 'id')->all()),
                ])
                ->action(function (array $data) {
                    $kelas = Classroom::findOrFail($data['classroom_id']);
                    // Penyaring sekolah membuat ID siswa sekolah lain "tidak ada"; permintaan yang tidak utuh ditolak, bukan diam-diam dikurangi.
                    $ids = array_values(array_unique(array_map('intval', $data['student_ids'])));
                    $siswaDitemukan = Student::whereIn('id', $ids)->get();
                    if ($siswaDitemukan->count() !== count($ids)) {
                        Notification::make()->danger()->title('Ada siswa yang tidak ditemukan di sekolah ini. Tidak ada yang ditempatkan.')->send();

                        return;
                    }
                    try {
                        DB::transaction(function () use ($kelas, $siswaDitemukan) {
                            foreach ($siswaDitemukan as $siswa) {
                                Enrollment::tempatkan($siswa, $kelas);
                            }
                        });
                    } catch (ValidationException $e) {
                        Notification::make()->danger()->persistent()->title('Penempatan dibatalkan, tidak ada siswa yang ditempatkan.')
                            ->body(collect($e->errors())->flatten()->implode(' '))->send();

                        return;
                    }
                    Notification::make()->success()->title(count($ids).' siswa ditempatkan di kelas '.$kelas->name.'.')->send();
                }),
        ];
    }

    private function siswaBelumPunyaKelas(?int $kelasId)
    {
        $tahun = $kelasId ? Classroom::whereKey($kelasId)->value('academic_year_id') : null;

        return Student::query()->where('status', 'aktif')->when($tahun, fn ($q) => $q->whereDoesntHave('enrollments', fn ($e) => $e->where('academic_year_id', $tahun)->where('aktif', 1)))
            ->when(! $tahun, fn ($q) => $q->whereRaw('1 = 0'));
    }
}
