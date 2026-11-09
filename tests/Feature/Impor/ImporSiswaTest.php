<?php

namespace Tests\Feature\Impor;

use App\Models\Staff;
use App\Models\Student;
use App\Modules\Impor\ImporSiswa;
use App\Modules\Impor\ImporStaf;
use App\Support\Tenancy\CurrentSchool;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\BuatXlsx;
use Tests\Concerns\SiapkanSekolah;
use Tests\TestCase;

class ImporSiswaTest extends TestCase
{
    use BuatXlsx, SiapkanSekolah;

    private function dalamSekolah(string $nama = 'SD A'): \App\Models\School
    {
        $s = $this->sekolah($nama);
        CurrentSchool::set($s->id);

        return $s;
    }

    private function impor(array $baris, ?string $header = null): \App\Modules\Impor\HasilImpor
    {
        return (new ImporSiswa)->jalankan($this->xlsx([$header ?? $this->headerSiswa(), ...$baris]));
    }

    public function test_berkas_valid_diimpor_semua(): void
    {
        $this->dalamSekolah();
        $h = $this->impor([
            ['Budi Santoso', '0012345678', '1001', 'L', 'Bandung', '2015-03-21'],
            ['Siti Aminah', '0012345679', '1002', 'P', 'Cimahi', '2015-05-02'],
            ['Andi', '', '', 'L', '', ''],
        ]);

        $this->assertTrue($h->berhasil(), json_encode($h->galat));
        $this->assertSame(3, $h->dibuat);
        $this->assertSame(3, Student::count());
        $this->assertSame('aktif', Student::where('name', 'Andi')->first()->status);
        $this->assertNull(Student::where('name', 'Andi')->first()->nisn); // kosong disimpan NULL
    }

    public function test_angka_nol_di_depan_nisn_dipertahankan_dan_sel_angka_diterima(): void
    {
        $this->dalamSekolah();
        $h = $this->impor([
            ['A', '0012345678', '', 'L', '', ''],   // teks: nol di depan aman
            ['B', 1234567890, '', 'P', '', ''],     // angka murni dari sel Excel
        ]);

        $this->assertTrue($h->berhasil(), json_encode($h->galat));
        $this->assertSame('0012345678', Student::where('name', 'A')->value('nisn'));
        $this->assertSame('1234567890', Student::where('name', 'B')->value('nisn'));
    }

    public function test_nisn_yang_kehilangan_nol_di_depan_ditolak_bukan_diam_diam_salah(): void
    {
        $this->dalamSekolah();
        $h = $this->impor([['A', 12345678, '', 'L', '', '']]); // 0012345678 yang tersimpan sebagai angka

        $this->assertFalse($h->berhasil());
        $this->assertSame(0, Student::count());
        $this->assertStringContainsString('10 digit', $h->galat[0]['pesan']);
    }

    public function test_tanggal_serial_excel_dan_teks_dikenali(): void
    {
        $this->dalamSekolah();
        $serial = Date::PHPToExcel(new \DateTime('2014-08-17'));
        $h = $this->impor([
            ['A', '', '', 'L', '', $serial],
            ['B', '', '', 'P', '', '21/03/2015'],
            ['C', '', '', 'P', '', '2016-01-05'],
        ]);

        $this->assertTrue($h->berhasil(), json_encode($h->galat));
        $this->assertSame('2014-08-17', Student::where('name', 'A')->first()->birth_date->format('Y-m-d'));
        $this->assertSame('2015-03-21', Student::where('name', 'B')->first()->birth_date->format('Y-m-d'));
        $this->assertSame('2016-01-05', Student::where('name', 'C')->first()->birth_date->format('Y-m-d'));
    }

    public function test_jenis_kelamin_ditulis_bermacam_cara_dinormalkan(): void
    {
        $this->dalamSekolah();
        $h = $this->impor([['A', '', '', 'Laki-laki', '', ''], ['B', '', '', 'perempuan', '', ''], ['C', '', '', 'p', '', '']]);

        $this->assertTrue($h->berhasil(), json_encode($h->galat));
        $this->assertSame(['L', 'P', 'P'], Student::orderBy('id')->pluck('gender')->all());
    }

    public function test_judul_kolom_toleran_huruf_dan_spasi_serta_kolom_asing_diabaikan(): void
    {
        $this->dalamSekolah();
        $h = (new ImporSiswa)->jalankan($this->xlsx([[' NAMA '], ['A']]));
        $this->assertFalse($h->berhasil()); // kolom wajib 'jenis kelamin' tidak ada
        $this->assertStringContainsString('jenis kelamin', $h->galat[0]['pesan']);

        $h = (new ImporSiswa)->jalankan($this->xlsx([['  nama ', 'JENIS   KELAMIN', 'Kolom Asing'], ['A', 'L', 'diabaikan']]));
        $this->assertTrue($h->berhasil(), json_encode($h->galat));
    }

    public function test_satu_baris_salah_membatalkan_seluruh_impor(): void
    {
        $this->dalamSekolah();
        $h = $this->impor([
            ['Valid 1', '', '', 'L', '', ''],
            ['', '', '', 'L', '', ''],            // baris 3: nama kosong
            ['Valid 2', '', '', 'P', '', ''],
        ]);

        $this->assertFalse($h->berhasil());
        $this->assertSame(0, $h->dibuat);
        $this->assertSame(0, Student::count(), 'baris valid tidak boleh tersimpan bila ada baris bermasalah');
        $this->assertSame(3, $h->galat[0]['baris']);
    }

    public function test_semua_kesalahan_dilaporkan_sekaligus_dengan_nomor_baris(): void
    {
        $this->dalamSekolah();
        $h = $this->impor([
            ['A', '123', '', 'L', '', ''],                  // baris 2: NISN bukan 10 digit
            ['B', '', '', 'X', '', ''],                     // baris 3: jenis kelamin salah
            ['C', '', '', 'L', '', '2999-01-01'],           // baris 4: tanggal lahir masa depan
            ['D', '', '', 'L', '', 'bukan tanggal'],        // baris 5: tanggal tidak dikenali
        ]);

        $this->assertSame([2, 3, 4, 5], array_values(array_unique(array_column($h->galat, 'baris'))));
        $this->assertSame(0, Student::count());
    }

    public function test_nisn_kembar_di_dalam_berkas_ditolak(): void
    {
        $this->dalamSekolah();
        $h = $this->impor([['A', '0012345678', '', 'L', '', ''], ['B', '0012345678', '', 'P', '', '']]);

        $this->assertFalse($h->berhasil());
        $this->assertSame(3, $h->galat[0]['baris']);
        $this->assertStringContainsString('kembar dengan baris 2', $h->galat[0]['pesan']);
    }

    public function test_nisn_yang_sudah_terdaftar_ditolak_tetapi_boleh_di_sekolah_lain(): void
    {
        $a = $this->dalamSekolah('SD A');
        $this->assertTrue($this->impor([['A', '0012345678', '', 'L', '', '']])->berhasil());

        $h = $this->impor([['Duplikat', '0012345678', '', 'L', '', '']]);
        $this->assertFalse($h->berhasil());
        $this->assertStringContainsString('sudah terdaftar', $h->galat[0]['pesan']);

        $this->dalamSekolah('SD B'); // sekolah lain memakai NISN yang sama: sah karena data terpisah
        $this->assertTrue($this->impor([['Lain', '0012345678', '', 'L', '', '']])->berhasil());
    }

    public function test_impor_hanya_menambah_tidak_mengubah_data_yang_ada(): void
    {
        $this->dalamSekolah();
        $this->impor([['Nama Asli', '0012345678', '', 'L', '', '']]);
        $this->impor([['Nama Lain', '0012345678', '', 'L', '', '']]);

        $this->assertSame('Nama Asli', Student::firstOrFail()->name);
    }

    public function test_isian_diawali_rumus_ditolak(): void
    {
        $this->dalamSekolah();
        $h = $this->impor([['=HYPERLINK("http://x")', '', '', 'L', '', ''], ['@SUM(A1)', '', '', 'P', '', '']]);

        $this->assertFalse($h->berhasil());
        $this->assertSame(0, Student::count());
    }

    public function test_baris_kosong_dilewati_dan_berkas_tanpa_data_ditolak(): void
    {
        $this->dalamSekolah();
        $this->assertTrue($this->impor([['A', '', '', 'L', '', ''], ['', '', '', '', '', ''], ['B', '', '', 'P', '', '']])->berhasil());
        $this->assertSame(2, Student::count());

        $h = $this->impor([]);
        $this->assertFalse($h->berhasil());
    }

    public function test_berkas_bukan_excel_ditolak(): void
    {
        $this->dalamSekolah();
        $f = tempnam(sys_get_temp_dir(), 'uji').'.xlsx';
        file_put_contents($f, "Nama,Jenis Kelamin\nA,L\n"); // CSV yang diberi nama .xlsx
        $h = (new ImporSiswa)->jalankan($f);
        @unlink($f);

        $this->assertFalse($h->berhasil());
        $this->assertStringContainsString('bukan Excel', $h->galat[0]['pesan']);
    }

    public function test_berkas_terlalu_besar_dan_terlalu_banyak_baris_ditolak(): void
    {
        $this->dalamSekolah();
        $besar = tempnam(sys_get_temp_dir(), 'uji');
        file_put_contents($besar, str_repeat('x', ImporSiswa::MAKS_BYTE + 1));
        $this->assertStringContainsString('terlalu besar', (new ImporSiswa)->jalankan($besar)->galat[0]['pesan']);
        @unlink($besar);

        $banyak = array_fill(0, ImporSiswa::MAKS_BARIS + 1, ['A', '', '', 'L', '', '']);
        $h = $this->impor($banyak);
        $this->assertStringContainsString('Terlalu banyak baris', $h->galat[0]['pesan']);
        $this->assertSame(0, Student::count());
    }

    public function test_impor_ratusan_baris_selesai_dengan_cepat(): void
    {
        $this->dalamSekolah();
        $baris = [];
        for ($i = 1; $i <= 400; $i++) {
            $baris[] = ["Siswa $i", sprintf('%010d', $i), "N$i", $i % 2 ? 'L' : 'P', 'Bandung', '2015-01-01'];
        }
        $t = microtime(true);
        $h = $this->impor($baris);

        $this->assertTrue($h->berhasil(), json_encode(array_slice($h->galat, 0, 3)));
        $this->assertSame(400, Student::count());
        $this->assertLessThan(20, microtime(true) - $t, 'impor 400 baris terlalu lambat');
    }

    public function test_impor_tercatat_sebagai_satu_ringkasan_yang_bisa_dilacak(): void
    {
        $this->dalamSekolah();
        $this->actingAs($this->adminDari(\App\Models\School::firstOrFail()));
        $this->impor([['A', '', '', 'L', '', ''], ['B', '', '', 'P', '', ''], ['C', '', '', 'L', '', '']]);

        $ringkasan = Activity::where('log_name', 'impor')->get();
        $this->assertCount(1, $ringkasan);
        $this->assertSame(3, $ringkasan[0]->properties['jumlah']);
        $this->assertSame(Student::min('id'), $ringkasan[0]->properties['id_pertama']);
        $this->assertSame(Student::max('id'), $ringkasan[0]->properties['id_terakhir']);
        $this->assertNotNull($ringkasan[0]->causer_id, 'pelaku impor harus tercatat');
        $this->assertSame(0, Activity::where('log_name', 'siswa')->count(), 'log per baris seharusnya diganti ringkasan');
    }

    public function test_log_aktivitas_aktif_kembali_setelah_impor(): void
    {
        $this->dalamSekolah();
        $this->impor([['A', '', '', 'L', '', '']]);
        $this->assertFalse(app(\Spatie\Activitylog\Support\ActivityLogStatus::class)->disabled());

        Student::create(['name' => 'Manual', 'gender' => 'L']);
        $this->assertSame(1, Activity::where('log_name', 'siswa')->where('event', 'created')->count());
    }

    public function test_impor_gagal_tidak_meninggalkan_log_mati_dan_tidak_mencatat_ringkasan(): void
    {
        $this->dalamSekolah();
        $this->impor([['', '', '', 'L', '', '']]);

        $this->assertFalse(app(\Spatie\Activitylog\Support\ActivityLogStatus::class)->disabled());
        $this->assertSame(0, Activity::where('log_name', 'impor')->count());
    }

    public function test_templat_yang_diunduh_bisa_langsung_diimpor_kembali(): void
    {
        $this->dalamSekolah();
        $f = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($f, (new ImporSiswa)->templat());

        $h = (new ImporSiswa)->jalankan($f);
        @unlink($f);

        $this->assertTrue($h->berhasil(), json_encode($h->galat));
        $this->assertSame('0012345678', Student::firstOrFail()->nisn); // nol di depan selamat lewat templat
    }

    // ---------- guru dan tenaga kependidikan ----------

    public function test_impor_staf_menormalkan_jenis_dan_menolak_nip_kembar(): void
    {
        $this->dalamSekolah();
        $head = ['Nama', 'NIP', 'Jenis', 'Telepon', 'Email'];
        $h = (new ImporStaf)->jalankan($this->xlsx([$head,
            ['Bu Sari', '198001012005012001', 'Guru', '0812', 'sari@contoh.test'],
            ['Pak Tata', '', 'Tenaga Kependidikan', '', ''],
        ]));
        $this->assertTrue($h->berhasil(), json_encode($h->galat));
        $this->assertSame(['guru', 'tendik'], Staff::orderBy('id')->pluck('kind')->all());

        $h = (new ImporStaf)->jalankan($this->xlsx([$head, ['Lain', '198001012005012001', 'guru', '', '']]));
        $this->assertFalse($h->berhasil());
        $this->assertSame(2, Staff::count());

        $h = (new ImporStaf)->jalankan($this->xlsx([$head, ['Salah', '', 'kepala', '', 'bukan-email']]));
        $this->assertCount(2, $h->galat); // jenis dan email sama-sama dilaporkan
    }

    public function test_pesan_kesalahan_berbahasa_indonesia(): void
    {
        $this->dalamSekolah();
        $h = $this->impor([['', '', '', 'L', '', '']]);

        $this->assertStringContainsString('wajib diisi', $h->galat[0]['pesan']);
        $this->assertStringNotContainsString('validation.', $h->galat[0]['pesan']);
    }
}
