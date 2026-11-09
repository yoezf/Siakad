<?php

namespace App\Modules\Rapor;

use App\Models\ReportCard;
use App\Models\ReportCardVersion;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mengesahkan rapor satu siswa pada satu semester: membuat salinan tetap (versi bernomor) lalu mengunci nilai dan
 * kehadirannya. Revisi memakai jalur yang sama setelah kunci dibuka (BukaKunciRapor) dan menghasilkan versi berikutnya.
 */
final class SahkanRapor
{
    public static function handle(User $oleh, Semester $semester, Student $siswa): ReportCardVersion
    {
        if ($oleh->school_id === null || $oleh->school_id !== $semester->school_id || $oleh->school_id !== $siswa->school_id) {
            throw new AuthorizationException('Anda tidak berhak mengesahkan rapor ini.');
        }

        return DB::transaction(function () use ($oleh, $semester, $siswa) {
            $rapor = ReportCard::where('semester_id', $semester->id)->where('student_id', $siswa->id)->lockForUpdate()->first();
            if ($rapor?->status === 'disahkan') {
                throw ValidationException::withMessages(['rapor' => "Rapor ini sudah disahkan (versi {$rapor->current_version}). Buka kunci terlebih dahulu bila perlu revisi."]);
            }

            $hasil = Susun::untuk($semester, $siswa);
            if ($hasil['kelas'] === null) {
                throw ValidationException::withMessages(['rapor' => implode(' ', $hasil['masalah'])]);
            }
            if (! Akses::bisaMengesahkan($oleh, $hasil['kelas'])) {
                throw new AuthorizationException('Anda tidak berhak mengesahkan rapor ini.');
            }
            if ($hasil['masalah'] !== []) {
                throw ValidationException::withMessages(['rapor' => 'Rapor belum bisa disahkan: '.implode(' ', $hasil['masalah'])]);
            }

            $rapor ??= new ReportCard(['semester_id' => $semester->id, 'student_id' => $siswa->id, 'classroom_id' => $hasil['kelas']->id]);
            $versi = ($rapor->current_version ?? 0) + 1;
            $snapshot = $hasil['snapshot'];
            $sekarang = now();
            $snapshot['pengesahan'] = ['oleh' => $oleh->name, 'pada' => $sekarang->toDateTimeString(), 'versi' => $versi];

            $rapor->forceFill(['classroom_id' => $hasil['kelas']->id, 'status' => 'disahkan', 'current_version' => $versi])->save();

            return ReportCardVersion::create([
                'report_card_id' => $rapor->id, 'version' => $versi, 'snapshot' => $snapshot,
                'revision_reason' => $versi > 1 ? $rapor->unlock_reason : null, 'approved_by' => $oleh->id, 'approved_at' => $sekarang,
            ]);
        });
    }
}
