<?php

namespace App\Modules\Wajah;

use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\FaceTemplate;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\User;
use App\Modules\Kehadiran\CatatKehadiranSiswa;
use App\Modules\Kehadiran\Status;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Absensi siswa dengan wajah. Pencocokan wajah terjadi di peramban; server hanya menyimpan data numerik wajah (bukan foto),
 * membagikannya kepada pencatat yang berhak untuk satu kelas, dan mencatat kehadiran harian "hadir". Server TIDAK dapat memastikan
 * pencocokan di peramban itu benar, jadi pencatatan dibatasi pada pengguna yang login dan berhak atas kelas itu, dan setiap catatan memuat pencatatnya.
 */
final class AbsensiWajah
{
    public const CATATAN = 'Absensi wajah';

    public static function aktif(User $user): bool
    {
        return (bool) $user->school?->isActive() && $user->school->hasFeature('absensi_wajah');
    }

    public static function bisaKelola(User $user): bool
    {
        return self::aktif($user) && $user->can('kelola-data-wajah');
    }

    public static function bisaMemindai(User $user, Classroom $kelas): bool
    {
        return self::aktif($user) && CatatKehadiranSiswa::bisa($user, $kelas);
    }

    /** @param  list<list<float>>  $vektor */
    public static function daftarkan(User $oleh, Student $siswa, array $vektor): FaceTemplate
    {
        if (! self::bisaKelola($oleh) || $oleh->school_id !== $siswa->school_id) {
            throw new AuthorizationException('Anda tidak berhak mendaftarkan wajah siswa.');
        }
        if ($siswa->status !== 'aktif') {
            throw ValidationException::withMessages(['student_id' => 'Hanya siswa aktif yang bisa didaftarkan wajahnya.']);
        }
        $t = FaceTemplate::where('student_id', $siswa->id)->first() ?? new FaceTemplate(['student_id' => $siswa->id]);
        $t->descriptors = array_values($vektor);
        $t->created_by = $oleh->id;
        $t->save();

        return $t;
    }

    public static function hapus(User $oleh, Student $siswa): void
    {
        if (! self::bisaKelola($oleh) || $oleh->school_id !== $siswa->school_id) {
            throw new AuthorizationException('Anda tidak berhak menghapus data wajah siswa.');
        }
        FaceTemplate::where('student_id', $siswa->id)->get()->each->delete();
    }

    /** @return list<int> id siswa yang sudah terdaftar wajahnya */
    public static function idTerdaftar(): array
    {
        return FaceTemplate::pluck('student_id')->all();
    }

    /** @return list<array{id: int, nama: string, vektor: list<list<float>>}> siswa kelas ini hari ini yang punya data wajah */
    public static function dataKelas(User $oleh, Classroom $kelas): array
    {
        if (! self::bisaMemindai($oleh, $kelas)) {
            throw new AuthorizationException('Anda tidak berhak memindai kelas ini.');
        }
        $siswa = Enrollment::where('classroom_id', $kelas->id)->whereDate('joined_at', '<=', today())
            ->where(fn ($q) => $q->whereNull('left_at')->orWhereDate('left_at', '>=', today()))->with('student')->get()->pluck('student')->filter()->unique('id');
        $templat = FaceTemplate::whereIn('student_id', $siswa->pluck('id'))->get()->keyBy('student_id');

        return $siswa->filter(fn ($s) => $templat->has($s->id))->map(fn ($s) => ['id' => $s->id, 'nama' => $s->name, 'vektor' => $templat[$s->id]->descriptors])->values()->all();
    }

    /** @return 'dicatat'|'sudah' 'sudah' bila siswa itu sudah punya catatan kehadiran harian hari ini (status lama tidak ditimpa) */
    public static function catatHadir(User $oleh, Classroom $kelas, int $siswaId): string
    {
        if (! self::bisaMemindai($oleh, $kelas)) {
            throw new AuthorizationException('Anda tidak berhak memindai kelas ini.');
        }
        $kunci = 'absen-wajah:'.$oleh->id;
        if (RateLimiter::tooManyAttempts($kunci, 120)) {
            throw ValidationException::withMessages(['student_id' => 'Terlalu banyak pencatatan dalam semenit. Tunggu sebentar.']);
        }
        RateLimiter::hit($kunci, 60);

        if (StudentAttendance::where('student_id', $siswaId)->whereDate('date', today())->whereNull('schedule_entry_id')->exists()) {
            return 'sudah';
        }
        try {
            CatatKehadiranSiswa::handle($oleh, $kelas, today()->toDateString(), null, [$siswaId => ['status' => Status::HADIR, 'note' => self::CATATAN]]);
        } catch (UniqueConstraintViolationException) {
            return 'sudah';
        }

        return 'dicatat';
    }
}
