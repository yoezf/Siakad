<?php

namespace App\Modules\Rapor;

use App\Models\AssessmentComponent;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Kehadiran\Rekap as RekapKehadiran;
use App\Modules\Penilaian\Rekap as RekapNilai;

/**
 * Menyusun isi rapor dari data yang berjalan (nilai, kehadiran, catatan wali, identitas) dan memeriksanya.
 * Hasilnya dipakai dua cara: pratinjau draf di layar, dan salinan tetap saat disahkan (SahkanRapor).
 * Snapshot hanya berisi teks, bilangan bulat, boolean, dan null (lihat Sidik).
 */
final class Susun
{
    /** Kelas siswa pada tahun ajaran semester itu: penempatan yang berlaku, atau yang terakhir bila sudah ditutup. */
    public static function kelasSiswa(Semester $semester, Student $siswa): ?Classroom
    {
        $kelasId = Enrollment::where('student_id', $siswa->id)->where('academic_year_id', $semester->academic_year_id)
            ->orderByRaw('aktif IS NULL')->orderByDesc('id')->value('classroom_id');

        return $kelasId ? Classroom::with('homeroom')->find($kelasId) : null;
    }

    /**
     * @return array{kelas: ?Classroom, snapshot: ?array, masalah: list<string>, peringatan: list<string>}
     *                                                                                                     masalah = menghalangi pengesahan; peringatan = boleh disahkan tetapi perlu diperhatikan
     */
    public static function untuk(Semester $semester, Student $siswa): array
    {
        $kelas = self::kelasSiswa($semester, $siswa);
        if (! $kelas) {
            return ['kelas' => null, 'snapshot' => null, 'masalah' => ['Siswa tidak terdaftar di kelas mana pun pada tahun ajaran semester ini.'], 'peringatan' => []];
        }

        $sekolah = School::findOrFail($siswa->school_id);
        $kkmSekolah = (int) $sekolah->setting('kkm');
        $komponen = AssessmentComponent::aktifBerurutan();
        $ringkas = RekapNilai::siswa($semester, $siswa, $kkmSekolah);
        $hadir = RekapKehadiran::siswa($siswa, $semester->start_date, $semester->end_date);
        $header = ReportCard::where('semester_id', $semester->id)->where('student_id', $siswa->id)->first();
        $catatan = $header?->homeroom_note;
        $teks = fn (?float $n) => $n === null ? null : number_format($n, 2, '.', '');

        $masalah = [];
        $peringatan = [];
        if ($komponen->isEmpty()) {
            $masalah[] = 'Belum ada komponen penilaian aktif.';
        }
        if ($ringkas['mapel'] === []) {
            $masalah[] = 'Belum ada mata pelajaran yang diampu untuk kelas siswa ini.';
        }
        $mapel = [];
        foreach ($ringkas['mapel'] as $m) {
            if ($m['akhir'] === null) {
                $masalah[] = "Nilai {$m['subject']->name} belum lengkap.";
            }
            $perKomponen = [];
            foreach ($komponen as $k) {
                $perKomponen[$k->name] = isset($m['nilai'][$k->id]) ? $teks($m['nilai'][$k->id]) : null;
            }
            $mapel[] = [
                'kode' => $m['subject']->code, 'nama' => $m['subject']->name, 'kkm' => $m['kkm'], 'nilai' => $perKomponen,
                'akhir' => $teks($m['akhir']), 'bulat' => $m['bulat'], 'predikat' => $m['predikat'], 'tuntas' => $m['tuntas'],
            ];
        }
        if ($hadir['total'] === 0) {
            $peringatan[] = 'Belum ada catatan kehadiran harian untuk semester ini.';
        }
        if ($catatan === null) {
            $peringatan[] = 'Catatan wali kelas belum diisi.';
        }
        if (trim((string) $sekolah->setting('kepala_sekolah_nama')) === '') {
            $peringatan[] = 'Nama kepala sekolah belum diisi di Profil sekolah; kolom tanda tangan akan kosong.';
        }

        $snapshot = [
            'sekolah' => ['nama' => $sekolah->name, 'alamat' => $sekolah->address, 'kota' => $sekolah->city],
            'format' => [
                'judul' => (string) $sekolah->setting('rapor_judul'), 'kota' => (string) $sekolah->setting('rapor_kota'),
                'tampil_kehadiran' => (bool) $sekolah->setting('rapor_tampil_kehadiran'), 'kurikulum' => (string) $sekolah->setting('kurikulum'),
            ],
            'semester' => ['tahun' => $semester->academicYear->name, 'label' => $semester->label, 'nomor' => (int) $semester->number,
                'mulai' => $semester->start_date->toDateString(), 'selesai' => $semester->end_date->toDateString()],
            'siswa' => ['nama' => $siswa->name, 'nis' => $siswa->nis, 'nisn' => $siswa->nisn, 'gender' => $siswa->gender],
            'kelas' => ['nama' => $kelas->name, 'tingkat' => (int) $kelas->grade, 'wali' => $kelas->homeroom?->name, 'wali_nip' => $kelas->homeroom?->nip],
            'kkm_sekolah' => $kkmSekolah,
            'komponen' => $komponen->map(fn ($k) => ['nama' => $k->name, 'bobot' => (int) $k->weight])->values()->all(),
            'mapel' => $mapel,
            'rata' => $teks($ringkas['rata']),
            'lengkap' => $ringkas['lengkap'],
            'kehadiran' => ['H' => $hadir['H'], 'S' => $hadir['S'], 'I' => $hadir['I'], 'A' => $hadir['A'], 'T' => $hadir['T'], 'total' => $hadir['total'], 'persen' => $hadir['persen']],
            'catatan' => $catatan,
            'kepala' => ['nama' => (string) $sekolah->setting('kepala_sekolah_nama'), 'nip' => (string) $sekolah->setting('kepala_sekolah_nip')],
        ];

        return ['kelas' => $kelas, 'snapshot' => $snapshot, 'masalah' => $masalah, 'peringatan' => $peringatan];
    }
}
