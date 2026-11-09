<?php

namespace App\Modules\Penilaian;

use App\Models\AssessmentComponent;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Score;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * Rekap nilai. Otorisasi dilakukan pemanggil (lihat Akses). Nilai mengikuti siswa, bukan kelas: siswa yang pindah kelas
 * di tengah semester tetap membawa nilainya.
 */
final class Rekap
{
    /**
     * Satu mata pelajaran di satu kelas: nilai per komponen, nilai akhir, ketuntasan, dan predikat tiap siswa.
     *
     * @return list<array{student: Student, nilai: array<int, float>, akhir: ?float, bulat: ?int, tuntas: ?bool, predikat: ?string}>
     */
    public static function kelas(Semester $semester, Classroom $kelas, Subject $mapel, int $kkmSekolah): array
    {
        $komponen = AssessmentComponent::aktifBerurutan();
        $kkm = $mapel->kkmBerlaku($kkmSekolah);
        $siswa = Enrollment::where('classroom_id', $kelas->id)->where('academic_year_id', $semester->academic_year_id)
            ->with('student')->get()->pluck('student')->filter()->unique('id')->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $nilai = Score::where('semester_id', $semester->id)->where('subject_id', $mapel->id)->whereIn('student_id', $siswa->pluck('id'))
            ->get()->groupBy('student_id');

        return $siswa->map(fn (Student $s) => self::baris($s, $komponen, $nilai->get($s->id, collect()), $kkm))->all();
    }

    /**
     * Semua mata pelajaran seorang siswa pada satu semester (yang diampu di kelasnya pada tahun ajaran itu), plus rata-rata.
     *
     * @return array{mapel: list<array>, rata: ?float, lengkap: bool}
     */
    public static function siswa(Semester $semester, Student $siswa, int $kkmSekolah): array
    {
        $komponen = AssessmentComponent::aktifBerurutan();
        $kelasIds = Enrollment::where('student_id', $siswa->id)->where('academic_year_id', $semester->academic_year_id)->pluck('classroom_id');
        $mapel = Subject::whereIn('id', TeachingAssignment::whereIn('classroom_id', $kelasIds)->select('subject_id'))->orderBy('name')->get();
        $nilai = Score::where('semester_id', $semester->id)->where('student_id', $siswa->id)->get()->groupBy('subject_id');

        $baris = $mapel->map(function (Subject $m) use ($siswa, $komponen, $nilai, $kkmSekolah) {
            return self::baris($siswa, $komponen, $nilai->get($m->id, collect()), $m->kkmBerlaku($kkmSekolah)) + ['subject' => $m, 'kkm' => $m->kkmBerlaku($kkmSekolah)];
        });
        $akhir = $baris->pluck('akhir')->filter(fn ($a) => $a !== null);

        return [
            'mapel' => $baris->all(),
            'rata' => $akhir->isEmpty() ? null : round($akhir->avg(), 2),
            'lengkap' => $baris->isNotEmpty() && $akhir->count() === $baris->count(),
        ];
    }

    /**
     * Riwayat perubahan nilai satu mata pelajaran di satu kelas: nilai lama, nilai baru, siapa, dan kapan (terbaru dahulu).
     *
     * @return list<array{waktu: \Carbon\Carbon, siswa: string, komponen: string, lama: ?string, baru: ?string, oleh: string}>
     */
    public static function riwayat(Semester $semester, Classroom $kelas, Subject $mapel, int $batas = 50): array
    {
        $siswaIds = Enrollment::where('classroom_id', $kelas->id)->where('academic_year_id', $semester->academic_year_id)->pluck('student_id');
        $skor = Score::where('semester_id', $semester->id)->where('subject_id', $mapel->id)->whereIn('student_id', $siswaIds)
            ->with(['student', 'component'])->get()->keyBy('id');

        return Activity::where('log_name', 'nilai')->where('subject_type', (new Score)->getMorphClass())->whereIn('subject_id', $skor->keys())
            ->with('causer')->latest('id')->limit($batas)->get()->map(function (Activity $a) use ($skor) {
                $s = $skor->get($a->subject_id);
                // activitylog v5 menyimpan perubahan atribut di kolom attribute_changes (bukan properties).
                $ubah = $a->attribute_changes ?? collect();

                return [
                    'waktu' => $a->created_at,
                    'siswa' => $s?->student?->name ?? '-',
                    'komponen' => $s?->component?->name ?? '-',
                    'lama' => isset($ubah['old']['value']) ? (string) $ubah['old']['value'] : null,
                    'baru' => isset($ubah['attributes']['value']) ? (string) $ubah['attributes']['value'] : null,
                    'oleh' => $a->causer?->name ?? '-',
                ];
            })->all();
    }

    private static function baris(Student $siswa, Collection $komponen, Collection $skorSiswa, int $kkm): array
    {
        $nilai = $skorSiswa->mapWithKeys(fn (Score $s) => [$s->component_id => (float) $s->value])->all();
        $akhir = Hitung::akhir($komponen, $nilai);

        return [
            'student' => $siswa,
            'nilai' => $nilai,
            'akhir' => $akhir,
            'bulat' => Hitung::bulat($akhir),
            'tuntas' => Hitung::tuntas($akhir, $kkm),
            'predikat' => Hitung::predikat($akhir, $kkm),
        ];
    }
}
