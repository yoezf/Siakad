<?php

namespace App\Modules\Laporan;

use App\Models\Classroom;
use App\Models\School;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Modules\Kehadiran\Rekap as RekapKehadiran;
use App\Modules\Penilaian\Akses as AksesNilai;
use App\Modules\Penilaian\Rekap as RekapNilai;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Laporan akademik per kelas dalam bentuk tabel umum, yang kemudian bisa dijadikan PDF atau Excel (lihat Ekspor).
 * Otorisasi diperiksa di sini, bukan di halaman, sehingga berlaku dari jalur mana pun.
 *
 * Bentuk tabel: judul, baris keterangan, kolom (daftar nama), baris (daftar nilai sel: teks, angka, atau null).
 */
final class Laporan
{
    public const JENIS = ['nilai' => 'Rekap nilai akhir', 'kehadiran' => 'Rekap kehadiran'];

    /** @return array{judul: string, sekolah: string, keterangan: list<string>, kolom: list<string>, baris: list<list<mixed>>} */
    public static function nilai(User $user, Semester $semester, Classroom $kelas): array
    {
        self::pastikan($user, $semester, $kelas, AksesNilai::bisaMelihat($user, $kelas));
        $kkm = AksesNilai::kkmSekolah($user);
        $mapel = Subject::whereIn('id', TeachingAssignment::where('classroom_id', $kelas->id)->select('subject_id'))->orderBy('name')->get();
        $siswa = RekapKehadiran::kelas($kelas, $semester->start_date, $semester->end_date); // daftar siswa kelas pada semester itu

        $baris = [];
        foreach ($siswa as $i => $r) {
            $s = $r['student'];
            $ringkas = RekapNilai::siswa($semester, $s, $kkm);
            $perMapel = collect($ringkas['mapel'])->keyBy(fn ($m) => $m['subject']->id);
            $baris[] = array_merge([$i + 1, $s->name, $s->nis], $mapel->map(fn (Subject $m) => $perMapel->get($m->id)['bulat'] ?? null)->all(), [$ringkas['lengkap'] ? $ringkas['rata'] : null]); // rata-rata sebagian mapel menyesatkan
        }

        return [
            'judul' => self::JENIS['nilai'],
            'sekolah' => School::findOrFail($kelas->school_id)->name,
            'keterangan' => ["Kelas {$kelas->name}", self::namaSemester($semester), 'Nilai akhir bulat; kosong bila komponen belum lengkap. Rata-rata hanya dihitung bila semua mata pelajaran sudah bernilai.'],
            'kolom' => array_merge(['No', 'Nama', 'NIS'], $mapel->pluck('name')->all(), ['Rata-rata']),
            'baris' => $baris,
        ];
    }

    /** @return array{judul: string, sekolah: string, keterangan: list<string>, kolom: list<string>, baris: list<list<mixed>>} */
    public static function kehadiran(User $user, Semester $semester, Classroom $kelas, ?Carbon $dari = null, ?Carbon $sampai = null): array
    {
        self::pastikan($user, $semester, $kelas, RekapKehadiran::bisaMelihat($user, $kelas));
        $dari ??= $semester->start_date->copy();
        $sampai ??= $semester->end_date->copy();
        if ($dari->gt($sampai)) {
            throw new AuthorizationException('Rentang tanggal tidak sah.');
        }

        $baris = [];
        foreach (RekapKehadiran::kelas($kelas, $dari, $sampai) as $i => $r) {
            $baris[] = [$i + 1, $r['student']->name, $r['student']->nis, $r['H'], $r['T'], $r['S'], $r['I'], $r['A'], $r['total'], $r['persen']];
        }

        return [
            'judul' => self::JENIS['kehadiran'],
            'sekolah' => School::findOrFail($kelas->school_id)->name,
            'keterangan' => ["Kelas {$kelas->name}", self::namaSemester($semester), 'Periode '.$dari->format('d-m-Y').' s.d. '.$sampai->format('d-m-Y'),
                'Persentase = (hadir + terlambat) dibagi hari tercatat; hanya kehadiran harian.'],
            'kolom' => ['No', 'Nama', 'NIS', 'Hadir', 'Terlambat', 'Sakit', 'Izin', 'Alpa', 'Hari tercatat', 'Kehadiran (%)'],
            'baris' => $baris,
        ];
    }

    private static function pastikan(User $user, Semester $semester, Classroom $kelas, bool $boleh): void
    {
        if (! $boleh || $semester->school_id !== $user->school_id || $kelas->school_id !== $user->school_id
            || $kelas->academic_year_id !== $semester->academic_year_id) {
            throw new AuthorizationException('Anda tidak berhak melihat laporan ini.');
        }
    }

    private static function namaSemester(Semester $s): string
    {
        $s->loadMissing('academicYear');

        return "Semester {$s->label} Tahun Ajaran {$s->academicYear->name}";
    }
}
