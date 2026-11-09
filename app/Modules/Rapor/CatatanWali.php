<?php

namespace App\Modules\Rapor;

use App\Models\ReportCard;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Menyimpan catatan wali kelas pada rapor. Terkunci selama rapor disahkan. */
final class CatatanWali
{
    public static function simpan(User $oleh, Semester $semester, Student $siswa, ?string $catatan): ReportCard
    {
        $kelas = $oleh->school_id === $semester->school_id && $oleh->school_id === $siswa->school_id ? Susun::kelasSiswa($semester, $siswa) : null;
        if (! $kelas || ! Akses::bisaMenyusun($oleh, $kelas)) {
            throw new AuthorizationException('Anda tidak berhak menyusun rapor siswa ini.');
        }

        return DB::transaction(function () use ($semester, $siswa, $kelas, $catatan) {
            $rapor = ReportCard::where('semester_id', $semester->id)->where('student_id', $siswa->id)->lockForUpdate()->first();
            if ($rapor?->status === 'disahkan') {
                throw ValidationException::withMessages(['homeroom_note' => 'Rapor sudah disahkan; catatan wali kelas terkunci. Buka kunci rapor untuk merevisi.']);
            }
            $rapor ??= new ReportCard(['semester_id' => $semester->id, 'student_id' => $siswa->id, 'classroom_id' => $kelas->id]);
            $rapor->fill(['classroom_id' => $kelas->id, 'homeroom_note' => $catatan])->save();

            return $rapor;
        });
    }
}
