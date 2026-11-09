<?php

namespace App\Modules\Ai;

/**
 * Perintah (prompt), skema JSON, dan penyaring hasil untuk kedua modul. Penyaringan dilakukan di server, bukan hanya diminta
 * lewat prompt: model bisa melanggar instruksi, dan bagian yang tidak terlihat tidak boleh berisi karangan.
 */
final class Skema
{
    public const VERSI_PERSETUJUAN = '2026-11-v2';

    public const TEKS_PERSETUJUAN = 'Saya menyetujui foto ini dikirim ke layanan AI Google Gemini untuk dianalisis dan hasilnya disimpan di sistem sekolah. '
        .'Saya memahami bahwa hasilnya adalah interpretasi tradisional untuk hiburan, bukan penilaian ilmiah, dan foto serta hasilnya dapat dihapus kapan saja.';

    public const KETERANGAN = [
        'Hasil palmistri dan fisiognomi adalah interpretasi tradisional atau budaya untuk hiburan, bukan penilaian ilmiah, psikologis, atau medis.',
        'Hasil tidak boleh dipakai untuk menilai kepribadian, kecerdasan, moral, kesehatan, asal-usul, atau masa depan seseorang, maupun untuk keputusan apa pun tentang siswa atau pegawai.',
    ];

    public const STATUS = ['terlihat_jelas' => 'Terlihat jelas', 'terlihat_samar' => 'Terlihat samar', 'tidak_terlihat' => 'Tidak terlihat'];

    public const KETERBACAAN = ['baik' => 'Baik', 'cukup' => 'Cukup', 'buruk' => 'Buruk'];

    public const BAGIAN = [
        'palmistry' => ['garis_kehidupan' => 'Garis kehidupan', 'garis_kepala' => 'Garis kepala', 'garis_hati' => 'Garis hati', 'garis_nasib' => 'Garis nasib',
            'bentuk_telapak' => 'Bentuk telapak tangan', 'bentuk_jari' => 'Bentuk jari'],
        'physiognomy' => ['bentuk_wajah' => 'Bentuk wajah', 'mata' => 'Bentuk mata', 'hidung' => 'Bentuk hidung', 'bibir' => 'Bentuk bibir', 'alis' => 'Bentuk alis',
            'dagu' => 'Bentuk dagu', 'proporsi_wajah' => 'Proporsi visual wajah'],
    ];

    /** Kata yang menandakan kesimpulan terlarang. Sengaja luas: lebih baik menyaring berlebihan daripada meloloskan. */
    private const TERLARANG = [
        'physiognomy' => '/\b(kecerdasan|cerdas|pintar|bodoh|iq|kepribadian|sifat|karakter|watak|moral|jahat|jujur|bohong|kriminal|etnis|suku|ras|keturunan|asia|kaukasia|negroid|mongoloid|agama|penyakit|kesehatan|sehat|sakit|nasib|takdir|jodoh|rezeki|kaya|miskin|sukses|gagal|masa depan|kebahagiaan|bakat|potensi)\b/iu',
        'palmistry' => '/\b(kematian|meninggal|wafat|penyakit|diagnosis|kanker|jantung|etnis|suku|ras|agama|kriminal|jahat)\b/iu',
    ];

    public static function prompt(string $jenis): string
    {
        $umum = "Kamu adalah asisten yang MENDESKRIPSIKAN isi foto secara visual dan menjelaskan tradisi budaya terkait, hanya untuk hiburan. Jawab hanya dalam JSON sesuai skema, "
            ."dalam bahasa Indonesia. Aturan wajib:\n"
            ."- Deskripsikan hanya yang benar-benar terlihat. Jika sebuah bagian tidak terlihat jelas, beri status \"tidak_terlihat\" dan kosongkan deskripsi serta interpretasinya. Jangan mengarang.\n"
            ."- Jika foto kabur, gelap, terpotong, atau bukan objek yang diminta, nilai keterbacaan \"buruk\" dan jelaskan alasannya.\n"
            ."- Pada kolom area, isi kotak pembatas objek utama [ymin, xmin, ymax, xmax] dengan skala 0 sampai 1000 terhadap ukuran foto. Isi array kosong bila objek tidak terdeteksi.\n"
            ."- Interpretasi hanyalah penjelasan tradisi budaya, ditulis dengan kata seperti \"dalam tradisi ... dipercaya\", bukan fakta.\n";

        return $jenis === 'palmistry'
            ? $umum."Tugas: analisis foto TELAPAK TANGAN (palmistri/chiromancy). Tentukan apakah foto memang telapak tangan, sisinya (kanan/kiri), lalu garis kehidupan, garis kepala, "
                ."garis hati, garis nasib (hanya bila terlihat), bentuk telapak tangan, dan bentuk jari. Tiap bagian punya status keterbacaan.\n"
                .'- Jangan menyebut penyakit, diagnosis, umur, atau kematian.'
            : $umum."Tugas: deskripsikan foto WAJAH TAMPAK DEPAN (fisiognomi). Tentukan apakah ada tepat satu wajah, lalu deskripsikan SECARA VISUAL bentuk wajah, mata, hidung, bibir, alis, "
                ."dagu, dan proporsi wajah. Kolom interpretasi_tradisi hanya boleh berisi penjelasan simbolik dari tradisi fisiognomi sebagai budaya atau hiburan.\n"
                .'- DILARANG menyimpulkan kepribadian, kecerdasan, moralitas, suku, etnis, ras, agama, kesehatan, atau nasib seseorang dari wajah. Jangan menyebut asal-usul atau kelompok orang.';
    }

    /** Skema respons untuk Gemini (subset OpenAPI). */
    public static function skema(string $jenis): array
    {
        $teks = ['type' => 'STRING'];
        $bagian = ['type' => 'OBJECT', 'properties' => [
            'status' => ['type' => 'STRING', 'enum' => array_keys(self::STATUS)],
            'deskripsi_visual' => $teks,
            'interpretasi_tradisi' => $teks,
        ], 'required' => ['status', 'deskripsi_visual', 'interpretasi_tradisi']];
        $props = [
            $jenis === 'palmistry' ? 'gambar_valid' : 'wajah_terdeteksi' => ['type' => 'BOOLEAN'],
            'keterbacaan_gambar' => ['type' => 'OBJECT', 'properties' => ['tingkat' => ['type' => 'STRING', 'enum' => array_keys(self::KETERBACAAN)], 'alasan' => $teks],
                'required' => ['tingkat', 'alasan']],
            'ringkasan_indonesia' => $teks,
            'catatan_keterbatasan' => ['type' => 'ARRAY', 'items' => $teks],
            'area' => ['type' => 'ARRAY', 'items' => ['type' => 'INTEGER']],
        ];
        $props += $jenis === 'palmistry' ? ['sisi_tangan' => ['type' => 'STRING', 'enum' => ['kanan', 'kiri', 'tidak_terlihat']]] : ['jumlah_wajah' => ['type' => 'INTEGER']];
        foreach (array_keys(self::BAGIAN[$jenis]) as $k) {
            $props[$k] = $bagian;
        }

        return ['type' => 'OBJECT', 'properties' => $props, 'required' => array_keys($props)];
    }

    /**
     * Menyaring respons mentah menjadi hasil terstruktur yang aman. Hanya kolom yang dikenal yang dipertahankan; status tidak sah dianggap
     * "tidak_terlihat"; bagian yang tidak terlihat dikosongkan; teks bermuatan kesimpulan terlarang diganti pemberitahuan.
     *
     * @return array{hasil: array, ringkasan: string, keterbacaan: string}
     */
    public static function bersihkan(string $jenis, array $mentah): array
    {
        $penanda = $jenis === 'palmistry' ? 'gambar_valid' : 'wajah_terdeteksi';
        $valid = filter_var($mentah[$penanda] ?? false, FILTER_VALIDATE_BOOLEAN);
        $tingkat = (string) ($mentah['keterbacaan_gambar']['tingkat'] ?? '');
        $tingkat = array_key_exists($tingkat, self::KETERBACAAN) ? $tingkat : 'buruk';
        $disaring = [];

        $aman = function (string $teks, string $kunci) use ($jenis, &$disaring): string {
            $teks = trim(strip_tags($teks));
            $teks = mb_substr($teks, 0, 600);
            if ($teks !== '' && preg_match(self::TERLARANG[$jenis], $teks)) {
                $disaring[] = $kunci;

                return 'Teks bagian ini disaring karena memuat kesimpulan yang tidak diizinkan.';
            }

            return $teks;
        };

        $bagian = [];
        foreach (self::BAGIAN[$jenis] as $kunci => $label) {
            $b = is_array($mentah[$kunci] ?? null) ? $mentah[$kunci] : [];
            $status = (string) ($b['status'] ?? '');
            $status = array_key_exists($status, self::STATUS) && $valid ? $status : 'tidak_terlihat';
            $tampak = $status !== 'tidak_terlihat';
            $bagian[$kunci] = [
                'label' => $label,
                'status' => $status,
                'deskripsi_visual' => $tampak ? $aman((string) ($b['deskripsi_visual'] ?? ''), $kunci) : '', // tidak terlihat: tidak ada karangan
                'interpretasi_tradisi' => $tampak ? $aman((string) ($b['interpretasi_tradisi'] ?? ''), $kunci) : '',
            ];
        }
        $terdeteksi = array_values(array_map(fn ($b) => $b['label'], array_filter($bagian, fn ($b) => $b['status'] !== 'tidak_terlihat')));
        $takTerdeteksi = array_values(array_map(fn ($b) => $b['label'], array_filter($bagian, fn ($b) => $b['status'] === 'tidak_terlihat')));

        $ringkasan = $valid ? $aman((string) ($mentah['ringkasan_indonesia'] ?? ''), 'ringkasan') : '';
        if ($ringkasan === '' || in_array('ringkasan', $disaring, true)) {
            $ringkasan = $valid
                ? sprintf('Analisis visual: %d bagian terdeteksi dan %d bagian tidak terdeteksi. Tingkat keterbacaan gambar: %s.', count($terdeteksi), count($takTerdeteksi), strtolower(self::KETERBACAAN[$tingkat]))
                : ($jenis === 'palmistry' ? 'Foto tidak dikenali sebagai telapak tangan, sehingga tidak ada bagian yang dianalisis.' : 'Tidak terdeteksi tepat satu wajah, sehingga tidak ada bagian yang dianalisis.');
        }

        $catatan = [];
        foreach ((array) ($mentah['catatan_keterbatasan'] ?? []) as $c) {
            if (is_string($c) && trim($c) !== '' && count($catatan) < 6) {
                $catatan[] = mb_substr(trim(strip_tags($c)), 0, 300);
            }
        }

        // Kotak pembatas objek [ymin, xmin, ymax, xmax] skala 0-1000; tidak sah atau objek tidak valid: tanpa kotak.
        $kotak = array_values(array_map('intval', array_filter((array) ($mentah['area'] ?? []), 'is_numeric')));
        $area = $valid && count($kotak) === 4 && min($kotak) >= 0 && max($kotak) <= 1000 && $kotak[0] < $kotak[2] && $kotak[1] < $kotak[3] ? $kotak : null;

        $hasil = [
            'jenis' => $jenis,
            'area' => $area,
            $penanda => $valid,
            'keterbacaan_gambar' => ['tingkat' => $tingkat, 'alasan' => mb_substr(trim(strip_tags((string) ($mentah['keterbacaan_gambar']['alasan'] ?? ''))), 0, 300)],
            'bagian' => $bagian,
            'bagian_terdeteksi' => $terdeteksi,
            'bagian_tidak_terdeteksi' => $takTerdeteksi,
            'ringkasan_indonesia' => $ringkasan,
            'catatan_keterbatasan' => array_merge($catatan, self::KETERANGAN),
            'disaring' => array_values(array_unique($disaring)),
        ];
        if ($jenis === 'palmistry') {
            $sisi = (string) ($mentah['sisi_tangan'] ?? '');
            $hasil['sisi_tangan'] = in_array($sisi, ['kanan', 'kiri'], true) ? $sisi : 'tidak_terlihat';
        } else {
            $hasil['jumlah_wajah'] = max(0, min(20, (int) ($mentah['jumlah_wajah'] ?? 0)));
        }

        return ['hasil' => $hasil, 'ringkasan' => $ringkasan, 'keterbacaan' => $tingkat];
    }
}
