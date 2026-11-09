<?php

namespace App\Modules\Penilaian;

use App\Models\AssessmentComponent;
use App\Models\Classroom;
use App\Models\Score;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menyimpan nilai satu mata pelajaran di satu kelas pada satu semester, semua atau tidak sama sekali.
 * Isian kosong berarti "tidak diubah" (bukan nol). Otorisasi dijaga di sini agar tidak bisa dilewati.
 */
final class CatatNilai
{
    /**
     * @param  array<int|string, array<int|string, mixed>>  $nilai  id siswa => (id komponen => nilai)
     * @return int jumlah nilai yang dibuat atau diubah
     */
    public static function handle(User $oleh, Semester $semester, Classroom $kelas, Subject $mapel, array $nilai): int
    {
        if (! Akses::bisaMenilai($oleh, $kelas, $mapel)) {
            throw new AuthorizationException('Anda tidak berhak menilai mata pelajaran ini di kelas ini.');
        }
        if (! Akses::semesterBoleh($oleh, $semester)) {
            throw ValidationException::withMessages(['semester_id' => 'Nilai hanya bisa diisi pada semester yang sedang aktif.']);
        }
        if ($kelas->academic_year_id !== $semester->academic_year_id) {
            throw ValidationException::withMessages(['classroom_id' => 'Kelas ini bukan kelas pada tahun ajaran semester tersebut.']);
        }
        $komponen = AssessmentComponent::aktifBerurutan()->keyBy('id');
        if ($komponen->isEmpty()) {
            throw ValidationException::withMessages(['component_id' => 'Belum ada komponen penilaian aktif. Atur dulu di menu Komponen penilaian.']);
        }

        return DB::transaction(function () use ($oleh, $semester, $kelas, $mapel, $nilai, $komponen) {
            $berubah = 0;
            foreach ($nilai as $siswaId => $perKomponen) {
                foreach ((array) $perKomponen as $komponenId => $mentah) {
                    $mentah = is_string($mentah) ? trim(str_replace(',', '.', $mentah)) : $mentah; // koma desimal gaya Indonesia
                    if ($mentah === null || $mentah === '') {
                        continue;
                    }
                    if (! is_numeric($mentah)) {
                        throw ValidationException::withMessages(['value' => 'Nilai harus berupa angka (contoh: 85 atau 85,5).']);
                    }
                    if (! $komponen->has((int) $komponenId)) {
                        throw ValidationException::withMessages(['component_id' => 'Ada komponen yang tidak aktif atau tidak ditemukan.']);
                    }
                    $skor = Score::where('semester_id', $semester->id)->where('student_id', (int) $siswaId)
                        ->where('subject_id', $mapel->id)->where('component_id', (int) $komponenId)->first();
                    if ($skor && (float) $skor->value === round((float) $mentah, 2) && $skor->classroom_id === $kelas->id) {
                        continue; // tidak berubah
                    }
                    $skor ??= new Score(['semester_id' => $semester->id, 'student_id' => (int) $siswaId, 'subject_id' => $mapel->id, 'component_id' => (int) $komponenId]);
                    $skor->fill(['classroom_id' => $kelas->id, 'value' => $mentah]);
                    $skor->recorded_by = $oleh->id;
                    $skor->save();
                    $berubah++;
                }
            }

            return $berubah;
        });
    }
}
