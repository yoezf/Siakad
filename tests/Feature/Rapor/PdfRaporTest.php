<?php

namespace Tests\Feature\Rapor;

use App\Models\ReportCardVersion;
use App\Modules\Rapor\CatatanWali;
use App\Modules\Rapor\PdfRapor;
use Illuminate\Support\Facades\DB;

class PdfRaporTest extends RaporTestCase
{
    private function html(ReportCardVersion $v): string
    {
        return $this->untuk($this->s, fn () => view('rapor.pdf', PdfRapor::data($v->fresh()))->render());
    }

    public function test_pdf_adalah_berkas_pdf_yang_sah_dan_tidak_kosong(): void
    {
        $this->siapSahkan();
        $v = $this->sahkan();

        $pdf = $this->untuk($this->s, fn () => PdfRapor::render($v->fresh()));

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(3000, strlen($pdf));
        $this->assertStringContainsString('%%EOF', $pdf);
    }

    public function test_nama_berkas_memuat_siswa_semester_dan_versi(): void
    {
        $this->siapSahkan();
        $v = $this->sahkan();

        $this->assertSame('rapor-Siswa-A1-2026-2027-ganjil-v1.pdf', PdfRapor::namaBerkas($v->fresh()));
    }

    public function test_isi_rapor_memuat_identitas_nilai_kehadiran_catatan_dan_tanda_tangan(): void
    {
        $this->siapSahkan();
        $h = $this->html($this->sahkan());

        foreach (['LAPORAN HASIL BELAJAR SISWA', 'SD A', 'Siswa A1', '4A', 'Semester Ganjil Tahun Ajaran 2026/2027', 'Matematika', 'Bahasa Indonesia', 'IPA',
            'Ulangan Harian', 'UTS', 'UAS', '(30%)', '(40%)', '76,33', 'Siswa rajin dan santun.', 'Bu Wali A', 'Drs. Kepala Sekolah', 'NIP 1970010119900101',
            'Bandung, 12 November 2026', 'Versi 1', 'Disahkan oleh Kepala'] as $teks) {
            $this->assertStringContainsString($teks, $h, "rapor harus memuat: $teks");
        }
        $this->assertStringContainsString('Hari tercatat', $h);
        $this->assertStringNotContainsString('TIDAK BERLAKU', $h);
    }

    public function test_mata_pelajaran_belum_tuntas_diberi_tanda(): void
    {
        $this->siapSahkan();
        $h = $this->html($this->sahkan());

        $this->assertStringContainsString('D*', $h, 'IPA 60 di bawah KKM 70');
        $this->assertStringContainsString('Belum mencapai kriteria ketuntasan minimal', $h);
    }

    public function test_kehadiran_disembunyikan_bila_sekolah_mematikannya(): void
    {
        $this->untuk($this->s, fn () => $this->s->update(['settings' => ['rapor_tampil_kehadiran' => false, 'kepala_sekolah_nama' => 'K']]));
        $this->siapSahkan();
        $h = $this->html($this->sahkan());

        $this->assertStringNotContainsString('Hari tercatat', $h);
        $this->assertStringContainsString('B. Catatan Wali Kelas', $h, 'penomoran bagian menyesuaikan');
    }

    public function test_versi_lama_diberi_tanda_tidak_berlaku_dan_versi_baru_tidak(): void
    {
        $this->siapSahkan();
        $v1 = $this->sahkan();
        $this->bukaKunci('Salah ketik nilai matematika');
        $this->nilai($this->guruMtk, $this->kelasA, $this->mtk, [$this->a1->id => [$this->uh->id => 100]]);
        $v2 = $this->sahkan();

        $h1 = $this->html($v1);
        $h2 = $this->html($v2);

        $this->assertStringContainsString('TIDAK BERLAKU', $h1);
        $this->assertStringContainsString('Versi ini sudah digantikan oleh versi 2.', $h1);
        $this->assertStringNotContainsString('TIDAK BERLAKU', $h2);
        $this->assertStringContainsString('revisi: Salah ketik nilai matematika', $h2);
        $this->assertStringNotContainsString('revisi:', $h1);
    }

    public function test_kode_verifikasi_berasal_dari_sidik_dan_berbeda_antar_versi(): void
    {
        $this->siapSahkan();
        $v1 = $this->sahkan();
        $this->bukaKunci();
        $v2 = $this->sahkan();

        $this->assertStringContainsString('Kode verifikasi: '.strtoupper(substr($v1->checksum, 0, 16)), $this->html($v1));
        $this->assertNotSame(substr($v1->checksum, 0, 16), substr($v2->checksum, 0, 16));
    }

    public function test_pdf_dibuat_dari_salinan_bukan_dari_data_yang_berjalan(): void
    {
        $this->siapSahkan();
        $v = $this->sahkan();

        $this->untuk($this->s, fn () => $this->a1->update(['name' => 'Nama Berubah Setelahnya']));
        $this->untuk($this->s, fn () => $this->s->update(['name' => 'Sekolah Berganti Nama']));

        $h = $this->html($v);
        $this->assertStringContainsString('Siswa A1', $h);
        $this->assertStringNotContainsString('Nama Berubah Setelahnya', $h);
        $this->assertStringNotContainsString('Sekolah Berganti Nama', $h);
    }

    public function test_isi_html_di_catatan_dan_nama_di_escape_agar_tidak_berjalan_di_pdf(): void
    {
        $this->untuk($this->s, fn () => $this->a1->update(['name' => 'Budi <img src=x onerror=alert(1)>']));
        $this->lengkapi();
        $this->untuk($this->s, fn () => CatatanWali::simpan($this->waliA, $this->sem, $this->a1, '<script>alert("x")</script> <b>tebal</b>'));
        $h = $this->html($this->sahkan());

        $this->assertStringNotContainsString('<script>', $h);
        $this->assertStringNotContainsString('<img src=x', $h);
        $this->assertStringContainsString('&lt;script&gt;', $h);
        $this->assertStringContainsString('&lt;b&gt;tebal&lt;/b&gt;', $h);
    }

    public function test_salinan_yang_diubah_di_luar_sistem_diberi_peringatan_pada_pdf(): void
    {
        $this->siapSahkan();
        $v = $this->sahkan();
        $diubah = $v->snapshot;
        $diubah['rata'] = '99.00';
        DB::table('report_card_versions')->where('id', $v->id)->update(['snapshot' => json_encode($diubah)]);

        $h = $this->html($v);

        $this->assertStringContainsString('PERINGATAN: isi salinan ini tidak cocok dengan sidiknya', $h);
    }

    public function test_tanpa_nama_kepala_sekolah_kolom_tanda_tangan_diberi_titik(): void
    {
        $this->untuk($this->s, fn () => $this->s->update(['settings' => ['kepala_sekolah_nama' => '']]));
        $this->siapSahkan();
        $h = $this->html($this->sahkan());

        $this->assertStringContainsString('.....................', $h);
        $this->assertStringNotContainsString('Drs. Kepala Sekolah', $h);
    }
}
