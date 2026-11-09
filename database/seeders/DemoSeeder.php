<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\AssessmentComponent;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\ReportCard;
use App\Models\ScheduleEntry;
use App\Models\School;
use App\Models\Semester;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\TimeSlot;
use App\Models\User;
use App\Modules\Kehadiran\CatatKehadiranGuru;
use App\Modules\Kehadiran\CatatKehadiranSiswa;
use App\Modules\Penilaian\CatatNilai;
use App\Modules\Platform\RegisterSchool;
use App\Modules\Rapor\BukaKunciRapor;
use App\Modules\Rapor\CatatanWali;
use App\Modules\Rapor\SahkanRapor;
use App\Support\Peran;
use App\Support\Tenancy\CurrentSchool;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Data demo untuk presentasi: dua sekolah berbeda jenjang (SD dan SMP) dengan data yang terisi lengkap dan akun untuk
 * setiap peran. Hanya untuk lingkungan lokal atau demo; dijalankan lewat `php artisan demo:isi`.
 * Angka acak memakai benih tetap, jadi hasilnya sama setiap kali dijalankan.
 */
class DemoSeeder extends Seeder
{
    public const SANDI = 'demo-sekolah-2026';

    /** @var list<array{sekolah: string, peran: string, nama: string, email: string}> */
    private array $akun = [];

    private int $nomorSiswa = 0;

    /** @var array<int, int> id siswa => kemampuan dasar (untuk membangkitkan nilai yang masuk akal) */
    private array $kemampuan = [];

    /** @var array<string, true> */
    private array $namaTerpakai = [];

    private const LAKI = ['Ahmad', 'Budi', 'Dimas', 'Rizky', 'Fajar', 'Bagas', 'Naufal', 'Aditya', 'Kevin', 'Rafi', 'Farhan', 'Gilang', 'Alif', 'Zidan', 'Raka', 'Arief', 'Yoga', 'Bima', 'Daffa', 'Farel'];

    private const PEREMPUAN = ['Aisyah', 'Bunga', 'Citra', 'Dewi', 'Putri', 'Nabila', 'Salsabila', 'Zahra', 'Alya', 'Kirana', 'Nadia', 'Syifa', 'Intan', 'Rania', 'Azzahra', 'Mawar', 'Laila', 'Sekar', 'Tiara', 'Vina'];

    private const MARGA = ['Pratama', 'Saputra', 'Wijaya', 'Hidayat', 'Kusuma', 'Ramadhan', 'Lestari', 'Maharani', 'Permata', 'Nugroho', 'Firmansyah', 'Santoso', 'Purnama', 'Setiawan', 'Utami', 'Anggraini', 'Rahmawati', 'Wulandari', 'Cahyono', 'Susanto', 'Maulana', 'Aditama'];

    public function run(): void
    {
        mt_srand(2026);
        $this->akun[] = ['sekolah' => 'Platform', 'peran' => 'Super Admin (tim pengembang)', 'nama' => 'Super Admin Demo', 'email' => 'super@demo.test'];
        $super = new User(['name' => 'Super Admin Demo', 'email' => 'super@demo.test', 'password' => self::SANDI]);
        $super->save();
        $super->assignRole(Peran::SUPER_ADMIN);

        $this->sd();
        $this->smp();

        Auth::logout();
        CurrentSchool::set(null);
    }

    /** @return list<array{sekolah: string, peran: string, nama: string, email: string}> */
    public function daftarAkun(): array
    {
        return $this->akun;
    }

    // ------------------------------------------------------------------ sekolah

    private function sd(): void
    {
        [$s, $admin, $kepala, $y, $sem] = $this->mulaiSekolah([
            'name' => 'SD Harapan Bangsa', 'city' => 'Bandung', 'plan' => 'lengkap', 'levels' => ['sd'], 'domain' => 'harapanbangsa.demo',
            'kepala' => ['Drs. H. Ahmad Fauzi, M.Pd.', '196805121994031004'], 'kkm' => 70, 'alamat' => 'Jl. Merdeka No. 45, Bandung', 'telepon' => '022-4231188',
        ]);

        $komp = $this->komponen([['Ulangan Harian', 30], ['UTS', 30], ['UAS', 40]]);
        $mapel = [];
        foreach ([['AGM', 'Pendidikan Agama dan Budi Pekerti'], ['PKN', 'Pendidikan Pancasila'], ['IND', 'Bahasa Indonesia'], ['MTK', 'Matematika', 75],
            ['IPAS', 'Ilmu Pengetahuan Alam dan Sosial'], ['ING', 'Bahasa Inggris'], ['PJOK', 'Pendidikan Jasmani, Olahraga, dan Kesehatan'], ['SBD', 'Seni dan Budaya']] as $m) {
            $mapel[$m[0]] = Subject::create(['code' => $m[0], 'name' => $m[1], 'kkm' => $m[2] ?? null]);
        }

        $guru = [
            'MTK' => $this->staf($s, 'Budi Santoso, S.Pd.', 'gurumtk@harapanbangsa.demo', Peran::GURU_MAPEL, 'Guru Matematika'),
            'IPAS' => $this->staf($s, 'Rina Marlina, S.Pd.', null, null),
            'ING' => $this->staf($s, 'Dewi Anggraini, S.Pd.', null, null),
            'PJOK' => $this->staf($s, 'Agus Setiawan, S.Pd.', null, null),
            'AGM' => $this->staf($s, 'Hasan Basri, S.Pd.I.', null, null),
            'SBD' => $this->staf($s, 'Yuli Astuti, S.Sn.', null, null),
        ];
        $wali = [
            '4A' => $this->staf($s, 'Siti Nurhaliza, S.Pd.', 'wali4a@harapanbangsa.demo', Peran::WALI_KELAS, 'Wali Kelas 4A'),
            '5A' => $this->staf($s, 'Ratna Sari, S.Pd.', 'wali5a@harapanbangsa.demo', Peran::WALI_KELAS, 'Wali Kelas 5A'),
            '6A' => $this->staf($s, 'Hendra Gunawan, S.Pd.', 'wali6a@harapanbangsa.demo', Peran::WALI_KELAS, 'Wali Kelas 6A'),
        ];
        $this->staf($s, 'Dedi Kurniawan', 'tu@harapanbangsa.demo', Peran::STAF_TU, 'Staf Tata Usaha', 'tendik');

        $kelas = [];
        foreach ([['4A', 4], ['5A', 5], ['6A', 6]] as [$nama, $tingkat]) {
            $kelas[$nama] = Classroom::create(['academic_year_id' => $y->id, 'name' => $nama, 'grade' => $tingkat, 'homeroom_staff_id' => $wali[$nama]->id]);
            foreach ($mapel as $kode => $m) {
                $pengampu = in_array($kode, ['IND', 'PKN'], true) ? $wali[$nama] : $guru[$kode];
                TeachingAssignment::create(['classroom_id' => $kelas[$nama]->id, 'subject_id' => $m->id, 'staff_id' => $pengampu->id]);
            }
        }

        $siswa = [];
        foreach ($kelas as $nama => $k) {
            $siswa[$nama] = $this->isiSiswa($k, 12, $sem);
        }
        $this->jadwal($s, array_values($kelas), $mapel);

        // Nilai: 4A dan 6A lengkap; 5A belum ada UAS (sedang berjalan); satu siswa 6A kekurangan satu nilai.
        foreach ($kelas as $nama => $k) {
            foreach (array_values($mapel) as $i => $m) {
                $this->nilaiMapel($admin, $sem, $k, $m, $siswa[$nama], $komp, $i, $nama === '5A' ? ['UAS'] : [], $nama === '6A' && $m->code === 'IPAS' ? [$siswa['6A'][11]->id => ['UAS']] : []);
            }
        }
        $this->kehadiranSiswa($admin, $sem, $kelas);
        $this->kehadiranGuru($admin, $s);

        $catatan = ['Anak yang rajin dan santun, pertahankan semangat belajarnya.', 'Perlu lebih aktif bertanya di kelas; nilainya terus meningkat.', 'Sangat baik dalam kerja kelompok. Tingkatkan ketelitian saat berhitung.',
            'Disiplin dan bertanggung jawab. Terus berlatih membaca setiap hari.', 'Cukup baik, perlu bimbingan tambahan di rumah untuk Matematika.'];
        foreach (['4A', '6A'] as $nama) {
            foreach ($siswa[$nama] as $i => $st) {
                CatatanWali::simpan($admin, $sem, $st, $catatan[$i % count($catatan)]);
            }
        }
        // Rapor: 4A tiga sudah disahkan; 6A sembilan disahkan, satu dalam revisi, satu siap sahkan, satu belum lengkap.
        foreach (array_slice($siswa['4A'], 0, 3) as $st) {
            SahkanRapor::handle($kepala, $sem, $st);
        }
        foreach (array_slice($siswa['6A'], 0, 10) as $st) {
            SahkanRapor::handle($kepala, $sem, $st);
        }
        BukaKunciRapor::handle($kepala, ReportCard::where('student_id', $siswa['6A'][9]->id)->firstOrFail(), 'Perbaikan nilai Bahasa Inggris yang salah input.');

        // Orang tua: satu akun dengan dua anak (kakak di 6A, adik di 4A) dan akun siswa untuk satu siswa 6A.
        $this->orangTua($s, $siswa, ['4A' => 3, '5A' => 1, '6A' => 3]);
        $ortu = $this->akunPeran($s, 'Ibu Ratna Dewi', 'ortu@harapanbangsa.demo', Peran::ORANG_TUA, 'Orang tua (dua anak: 6A dan 4A)');
        $g = Guardian::create(['name' => 'Ratna Dewi', 'relationship' => 'ibu', 'phone' => '081234567890', 'user_id' => $ortu->id]);
        $g->linkStudents([$siswa['6A'][0]->id, $siswa['4A'][0]->id]);
        $akunSiswa = $this->akunPeran($s, $siswa['6A'][1]->name, 'siswa@harapanbangsa.demo', Peran::SISWA, 'Siswa kelas 6A');
        $siswa['6A'][1]->update(['user_id' => $akunSiswa->id]);

        $this->pengumuman($s, $kepala, $kelas['6A'], [
            ['Libur Semester Ganjil', "Kegiatan belajar mengajar libur mulai 20 Desember sampai 3 Januari.\nSiswa masuk kembali pada hari Senin, 4 Januari.", 'semua', 'terbit'],
            ['Pembagian Rapor Semester Ganjil', 'Rapor dibagikan pada hari Sabtu pukul 08.00. Mohon orang tua atau wali hadir bersama putra-putrinya.', 'orang_tua', 'terbit'],
            ['Try Out Kelas 6', 'Try out ujian sekolah dilaksanakan pekan depan. Siswa membawa alat tulis sendiri dan hadir 15 menit lebih awal.', 'kelas', 'terbit'],
            ['Lomba Menggambar Antar Sekolah', 'Pendaftaran lomba menggambar dibuka sampai akhir bulan. Daftarkan diri melalui wali kelas.', 'siswa', 'terbit'],
            ['Jadwal Ujian Akhir Semester (draf)', 'Jadwal masih disusun dan akan diumumkan setelah rapat guru.', 'semua', 'draf'],
        ]);
    }

    private function smp(): void
    {
        [$s, $admin, $kepala, $y, $sem] = $this->mulaiSekolah([
            'name' => 'SMP Cendekia Nusantara', 'city' => 'Surabaya', 'plan' => 'lengkap', 'levels' => ['smp'], 'domain' => 'cendekia.demo',
            'kepala' => ['Dr. Maya Sari Dewanti, M.Si.', '197304171998022001'], 'kkm' => 75, 'alamat' => 'Jl. Pemuda No. 12, Surabaya', 'telepon' => '031-5678123',
        ]);

        $komp = $this->komponen([['Tugas', 20], ['Ulangan Harian', 20], ['PTS', 25], ['PAS', 35]]);
        $mapel = [];
        foreach ([['IND', 'Bahasa Indonesia'], ['MTK', 'Matematika'], ['IPA', 'Ilmu Pengetahuan Alam'], ['IPS', 'Ilmu Pengetahuan Sosial'], ['ING', 'Bahasa Inggris'],
            ['PJOK', 'PJOK'], ['INF', 'Informatika']] as $m) {
            $mapel[$m[0]] = Subject::create(['code' => $m[0], 'name' => $m[1]]);
        }
        $guru = [];
        foreach ([['MTK', 'Hartono, S.Pd.'], ['IPA', 'Lestari Handayani, S.Pd.'], ['IPS', 'Bambang Eko, S.Pd.'], ['ING', 'Fitri Rahmawati, S.Pd.'], ['PJOK', 'Doni Prasetyo, S.Pd.'], ['INF', 'Wahyu Nugroho, S.Kom.']] as [$kode, $nama]) {
            $guru[$kode] = $this->staf($s, $nama, null, null);
        }
        $wali = ['7A' => $this->staf($s, 'Sri Wahyuni, S.Pd.', 'wali7a@cendekia.demo', Peran::WALI_KELAS, 'Wali Kelas 7A'),
            '8A' => $this->staf($s, 'Rudi Hermawan, S.Pd.', 'wali8a@cendekia.demo', Peran::WALI_KELAS, 'Wali Kelas 8A')];

        $kelas = [];
        foreach ([['7A', 7], ['8A', 8]] as [$nama, $tingkat]) {
            $kelas[$nama] = Classroom::create(['academic_year_id' => $y->id, 'name' => $nama, 'grade' => $tingkat, 'homeroom_staff_id' => $wali[$nama]->id]);
            foreach ($mapel as $kode => $m) {
                TeachingAssignment::create(['classroom_id' => $kelas[$nama]->id, 'subject_id' => $m->id, 'staff_id' => $kode === 'IND' ? $wali[$nama]->id : $guru[$kode]->id]);
            }
        }
        $siswa = [];
        foreach ($kelas as $nama => $k) {
            $siswa[$nama] = $this->isiSiswa($k, 10, $sem);
        }
        $this->jadwal($s, array_values($kelas), $mapel);
        foreach ($kelas as $nama => $k) {
            foreach (array_values($mapel) as $i => $m) {
                $this->nilaiMapel($admin, $sem, $k, $m, $siswa[$nama], $komp, $i, [], []);
            }
        }
        $this->kehadiranSiswa($admin, $sem, $kelas);
        $this->kehadiranGuru($admin, $s);
        foreach ($siswa['7A'] as $i => $st) {
            CatatanWali::simpan($admin, $sem, $st, 'Berkembang baik dan aktif dalam diskusi kelas.');
        }
        foreach (array_slice($siswa['7A'], 0, 6) as $st) {
            SahkanRapor::handle($kepala, $sem, $st);
        }

        $this->orangTua($s, $siswa, ['7A' => 2, '8A' => 2]);
        $ortu = $this->akunPeran($s, 'Bapak Hendro Wibowo', 'ortu@cendekia.demo', Peran::ORANG_TUA, 'Orang tua kelas 7A');
        $g = Guardian::create(['name' => 'Hendro Wibowo', 'relationship' => 'ayah', 'phone' => '081355501234', 'user_id' => $ortu->id]);
        $g->linkStudents([$siswa['7A'][0]->id]);

        $this->pengumuman($s, $kepala, $kelas['8A'], [
            ['Pekan Ujian Tengah Semester', 'Penilaian tengah semester berlangsung pekan depan. Siswa diharapkan mempersiapkan diri.', 'semua', 'terbit'],
            ['Kegiatan Pramuka Kelas 8', 'Perkemahan Sabtu-Minggu diikuti seluruh siswa kelas 8. Surat izin dikumpulkan ke wali kelas.', 'kelas', 'terbit'],
        ]);
    }

    // ------------------------------------------------------------------ bagian-bagian

    /** @return array{0: School, 1: User, 2: User, 3: AcademicYear, 4: Semester} */
    private function mulaiSekolah(array $d): array
    {
        $s = app(RegisterSchool::class)->handle(['name' => $d['name'], 'city' => $d['city'], 'plan' => $d['plan'], 'admin_name' => 'Admin '.$d['name'],
            'admin_email' => 'admin@'.$d['domain'], 'admin_password' => self::SANDI]);
        $s->update(['levels' => $d['levels'], 'address' => $d['alamat'], 'phone' => $d['telepon'], 'contact_email' => 'info@'.$d['domain'], 'settings' => [
            'kurikulum' => 'Kurikulum Merdeka', 'kkm' => $d['kkm'], 'rapor_kota' => $d['city'], 'rapor_judul' => 'LAPORAN HASIL BELAJAR PESERTA DIDIK',
            'kepala_sekolah_nama' => $d['kepala'][0], 'kepala_sekolah_nip' => $d['kepala'][1], 'rapor_tampil_kehadiran' => true, 'absensi_guru' => true]]);
        CurrentSchool::set($s->id);
        $admin = User::where('email', 'admin@'.$d['domain'])->firstOrFail();
        Auth::setUser($admin);
        $this->akun[] = ['sekolah' => $d['name'], 'peran' => 'Admin sekolah', 'nama' => $admin->name, 'email' => $admin->email];
        $kepala = $this->akunPeran($s, $d['kepala'][0], 'kepala@'.$d['domain'], Peran::KEPALA_SEKOLAH, 'Kepala sekolah');

        $now = Carbon::today();
        $mulai = $now->month >= 7 ? $now->year : $now->year - 1;
        $y = AcademicYear::create(['name' => $mulai.'/'.($mulai + 1), 'start_date' => "$mulai-07-01", 'end_date' => ($mulai + 1).'-06-30']);
        $sem1 = Semester::create(['academic_year_id' => $y->id, 'number' => 1, 'start_date' => "$mulai-07-01", 'end_date' => "$mulai-12-31"]);
        $sem2 = Semester::create(['academic_year_id' => $y->id, 'number' => 2, 'start_date' => ($mulai + 1).'-01-01', 'end_date' => ($mulai + 1).'-06-30']);
        $sem = $now->month >= 7 ? $sem1 : $sem2;
        $sem->activate();

        return [$s, $admin, $kepala, $y, Semester::findOrFail($sem->id)];
    }

    private function akunPeran(School $s, string $nama, string $email, string $peran, string $keterangan): User
    {
        $u = new User(['name' => $nama, 'email' => $email, 'password' => self::SANDI]);
        $u->school_id = $s->id;
        $u->save();
        $u->assignRole($peran);
        $this->akun[] = ['sekolah' => $s->name, 'peran' => $keterangan, 'nama' => $nama, 'email' => $email];

        return $u;
    }

    private function staf(School $s, string $nama, ?string $email, ?string $peran, string $keterangan = '', string $jenis = 'guru'): Staff
    {
        $akun = $email ? $this->akunPeran($s, $nama, $email, $peran, $keterangan) : null;

        return Staff::create(['name' => $nama, 'nip' => $jenis === 'guru' ? '19'.mt_rand(70, 92).sprintf('%02d%02d', mt_rand(1, 12), mt_rand(1, 28)).'20'.mt_rand(10, 15).'0'.mt_rand(1, 2).sprintf('%03d', mt_rand(1, 999)) : null,
            'kind' => $jenis, 'phone' => '08'.mt_rand(11, 99).mt_rand(1000000, 9999999), 'user_id' => $akun?->id]);
    }

    /** @return array<string, AssessmentComponent> */
    private function komponen(array $daftar): array
    {
        $hasil = [];
        foreach ($daftar as $i => [$nama, $bobot]) {
            $hasil[$nama] = AssessmentComponent::create(['name' => $nama, 'weight' => $bobot, 'sort_order' => $i + 1, 'active' => true]);
        }

        return $hasil;
    }

    private function nama(bool $perempuan): string
    {
        do {
            $n = ($perempuan ? self::PEREMPUAN : self::LAKI)[mt_rand(0, 19)].' '.self::MARGA[mt_rand(0, 21)];
        } while (isset($this->namaTerpakai[$n]));
        $this->namaTerpakai[$n] = true;

        return $n;
    }

    /** @return list<Student> */
    private function isiSiswa(Classroom $k, int $jumlah, Semester $sem): array
    {
        $daftar = [];
        for ($i = 0; $i < $jumlah; $i++) {
            $p = $i % 2 === 1;
            $lahir = Carbon::create($sem->start_date->year - ($k->grade + 6), mt_rand(1, 12), mt_rand(1, 28));
            $st = Student::create(['name' => $this->nama($p), 'gender' => $p ? 'P' : 'L', 'nis' => sprintf('%d%04d', $sem->start_date->year, ++$this->nomorSiswa),
                'nisn' => sprintf('%010d', 3100000000 + $this->nomorSiswa * 7919), 'birth_place' => $k->school->city ?? 'Bandung', 'birth_date' => $lahir->toDateString()]);
            Enrollment::tempatkan($st, $k);
            $this->kemampuan[$st->id] = mt_rand(70, 96);
            $daftar[] = $st;
        }
        DB::table('enrollments')->where('classroom_id', $k->id)->update(['joined_at' => $sem->start_date->toDateString()]);

        return $daftar;
    }

    /**
     * @param  list<string>  $tanpaKomponen  komponen yang belum dinilai untuk seluruh kelas
     * @param  array<int, list<string>>  $tanpaSiswa  id siswa => komponen yang belum dinilai
     */
    private function nilaiMapel(User $admin, Semester $sem, Classroom $k, Subject $m, array $siswa, array $komp, int $idx, array $tanpaKomponen, array $tanpaSiswa): void
    {
        $matriks = [];
        foreach ($siswa as $st) {
            foreach ($komp as $nama => $c) {
                if (in_array($nama, $tanpaKomponen, true) || in_array($nama, $tanpaSiswa[$st->id] ?? [], true)) {
                    continue;
                }
                $matriks[$st->id][$c->id] = max(55, min(100, $this->kemampuan[$st->id] + mt_rand(-12, 12) + (($idx % 3) - 1) * 3));
            }
        }
        if ($matriks !== []) {
            CatatNilai::handle($admin, $sem, $k, $m, $matriks);
        }
    }

    /** @return list<string> */
    private function hariKerja(Semester $sem, int $jumlah): array
    {
        $hari = [];
        for ($d = Carbon::today(); count($hari) < $jumlah && $d->gte($sem->start_date); $d = $d->copy()->subDay()) {
            if ($d->isWeekday()) {
                $hari[] = $d->toDateString();
            }
        }

        return array_reverse($hari);
    }

    private function status(): string
    {
        $r = mt_rand(1, 100);

        return $r <= 90 ? 'H' : ($r <= 94 ? 'T' : ($r <= 97 ? 'S' : ($r <= 99 ? 'I' : 'A')));
    }

    private function kehadiranSiswa(User $admin, Semester $sem, array $kelas): void
    {
        foreach ($this->hariKerja($sem, 20) as $tgl) {
            foreach ($kelas as $k) {
                $baris = [];
                foreach (Enrollment::where('classroom_id', $k->id)->pluck('student_id') as $id) {
                    $baris[$id] = ['status' => $this->status()];
                }
                CatatKehadiranSiswa::handle($admin, $k, $tgl, null, $baris);
            }
        }
    }

    private function kehadiranGuru(User $admin, School $s): void
    {
        $ids = Staff::where('kind', 'guru')->pluck('id');
        foreach ($this->hariKerja(Semester::where('aktif', 1)->firstOrFail(), 10) as $tgl) {
            $baris = [];
            foreach ($ids as $id) {
                $baris[$id] = ['status' => mt_rand(1, 100) <= 94 ? 'H' : 'S'];
            }
            CatatKehadiranGuru::handle($admin, $tgl, $baris);
        }
    }

    /** @param  array<string, Subject>  $mapel */
    private function jadwal(School $s, array $kelas, array $mapel): void
    {
        $jam = [['Jam ke-1', '07:00', '07:35'], ['Jam ke-2', '07:35', '08:10'], ['Jam ke-3', '08:10', '08:45'], ['Jam ke-4', '08:45', '09:20'], ['Istirahat', '09:20', '09:50', true],
            ['Jam ke-5', '09:50', '10:25'], ['Jam ke-6', '10:25', '11:00'], ['Jam ke-7', '11:00', '11:35']];
        $slot = [];
        foreach ($jam as $i => $j) {
            $slot[] = TimeSlot::create(['number' => $i + 1, 'name' => $j[0], 'start_time' => $j[1], 'end_time' => $j[2], 'is_break' => $j[3] ?? false]);
        }
        $pelajaran = array_values($mapel);
        $n = count($pelajaran);
        foreach ($kelas as $ci => $k) {
            for ($hari = 1; $hari <= 5; $hari++) {
                $dipakai = [];
                foreach (array_values(array_filter($slot, fn ($t) => ! $t->is_break)) as $si => $t) {
                    for ($c = 0; $c < $n; $c++) {
                        $m = $pelajaran[($si + $hari * 2 + $ci * 3 + $c) % $n];
                        if (in_array($m->id, $dipakai, true)) {
                            continue;
                        }
                        try {
                            ScheduleEntry::create(['classroom_id' => $k->id, 'subject_id' => $m->id, 'time_slot_id' => $t->id, 'day' => $hari]);
                            $dipakai[] = $m->id;
                            break;
                        } catch (ValidationException) {
                            continue; // guru yang sama sudah mengajar di kelas lain pada jam itu; coba pelajaran berikutnya
                        }
                    }
                }
            }
        }
    }

    /** Orang tua tanpa akun masuk untuk sebagian siswa, agar daftar orang tua terlihat terisi. @param array<string, int> $berapa */
    private function orangTua(School $s, array $siswa, array $berapa): void
    {
        foreach ($berapa as $nama => $jumlah) {
            foreach (array_slice($siswa[$nama], 0, $jumlah) as $st) {
                $marga = explode(' ', $st->name, 2)[1] ?? $st->name;
                $ayah = mt_rand(0, 1) === 1;
                Guardian::create(['name' => ($ayah ? self::LAKI : self::PEREMPUAN)[mt_rand(0, 19)].' '.$marga, 'relationship' => $ayah ? 'ayah' : 'ibu',
                    'phone' => '08'.mt_rand(11, 99).mt_rand(1000000, 9999999)])->linkStudents([$st->id]);
            }
        }
    }

    /** @param  list<array{0: string, 1: string, 2: string, 3: string}>  $daftar */
    private function pengumuman(School $s, User $oleh, Classroom $kelas, array $daftar): void
    {
        foreach ($daftar as [$judul, $isi, $sasaran, $status]) {
            Announcement::create(['title' => $judul, 'body' => $isi, 'audience' => $sasaran, 'status' => $status, 'classroom_id' => $sasaran === 'kelas' ? $kelas->id : null]);
        }
    }
}
