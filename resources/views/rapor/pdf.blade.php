<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $s['format']['judul'] ?: 'Rapor' }} - {{ $s['siswa']['nama'] }}</title>
    <style>
        @page { margin: 22mm 18mm 20mm 18mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; color: #123047; }
        h1 { font-size: 14pt; text-align: center; margin: 0 0 2mm; letter-spacing: .5pt; }
        .sub { text-align: center; margin: 0 0 6mm; font-size: 9.5pt; }
        table { border-collapse: collapse; width: 100%; }
        .id td { padding: 1mm 2mm; border: 0; vertical-align: top; }
        .nilai th, .nilai td { border: 1px solid #7b8a96; padding: 1.6mm 2mm; }
        .nilai th { background: #e8f3f6; text-align: center; font-size: 9pt; }
        .c { text-align: center; }
        .judul { font-weight: bold; margin: 6mm 0 2mm; }
        .ttd td { width: 50%; text-align: center; padding-top: 2mm; vertical-align: top; }
        .garis { margin-top: 18mm; font-weight: bold; text-decoration: underline; }
        .kecil { font-size: 8pt; color: #4a5f70; }
        .tidak-berlaku { position: fixed; top: 38%; left: 0; width: 100%; text-align: center; font-size: 34pt; color: #d9c3c3; font-weight: bold; }
        .catatan { border: 1px solid #7b8a96; padding: 2mm 3mm; min-height: 14mm; }
    </style>
</head>
<body>
@php($tidakBerlaku = $versi < $versiBerlaku)
@if ($tidakBerlaku)
    <div class="tidak-berlaku">TIDAK BERLAKU</div>
@endif

<h1>{{ $s['format']['judul'] ?: 'LAPORAN HASIL BELAJAR' }}</h1>
<p class="sub">
    <strong>{{ $s['sekolah']['nama'] }}</strong><br>
    @if ($s['sekolah']['alamat']) {{ $s['sekolah']['alamat'] }}<br> @endif
    Semester {{ $s['semester']['label'] }} Tahun Ajaran {{ $s['semester']['tahun'] }}
    @if ($s['format']['kurikulum']) &nbsp;|&nbsp; {{ $s['format']['kurikulum'] }} @endif
</p>

<table class="id">
    <tr><td style="width:22%">Nama</td><td style="width:3%">:</td><td style="width:45%"><strong>{{ $s['siswa']['nama'] }}</strong></td><td style="width:12%">Kelas</td><td style="width:3%">:</td><td>{{ $s['kelas']['nama'] }}</td></tr>
    <tr><td>NISN / NIS</td><td>:</td><td>{{ $s['siswa']['nisn'] ?: '-' }} / {{ $s['siswa']['nis'] ?: '-' }}</td><td>Tingkat</td><td>:</td><td>{{ $s['kelas']['tingkat'] }}</td></tr>
</table>

<p class="judul">A. Capaian Belajar</p>
<table class="nilai">
    <thead>
        <tr>
            <th style="width:5%">No</th>
            <th>Mata pelajaran</th>
            <th style="width:9%">KKM</th>
            @foreach ($s['komponen'] as $k)
                <th style="width:{{ max(8, intdiv(40, max(1, count($s['komponen'])))) }}%">{{ $k['nama'] }}<br><span class="kecil">({{ $k['bobot'] }}%)</span></th>
            @endforeach
            <th style="width:9%">Nilai akhir</th>
            <th style="width:9%">Predikat</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($s['mapel'] as $i => $m)
            <tr>
                <td class="c">{{ $i + 1 }}</td>
                <td>{{ $m['nama'] }}</td>
                <td class="c">{{ $m['kkm'] }}</td>
                @foreach ($s['komponen'] as $k)
                    <td class="c">{{ isset($m['nilai'][$k['nama']]) ? rtrim(rtrim(number_format((float) $m['nilai'][$k['nama']], 2, ',', ''), '0'), ',') : '-' }}</td>
                @endforeach
                <td class="c"><strong>{{ $m['bulat'] ?? '-' }}</strong></td>
                <td class="c">{{ $m['predikat'] ?? '-' }}{{ $m['tuntas'] === false ? '*' : '' }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="{{ 3 + count($s['komponen']) }}" style="text-align:right"><strong>Rata-rata</strong></td>
            <td class="c"><strong>{{ $s['rata'] === null ? '-' : number_format((float) $s['rata'], 2, ',', '') }}</strong></td>
            <td></td>
        </tr>
    </tbody>
</table>
<p class="kecil">* Belum mencapai kriteria ketuntasan minimal (KKM). Predikat: A (90 ke atas), B (80 ke atas), C (mencapai KKM), D (di bawah KKM).</p>

@if ($s['format']['tampil_kehadiran'])
    <p class="judul">B. Kehadiran</p>
    <table class="nilai" style="width:70%">
        <tr><th>Hadir</th><th>Terlambat</th><th>Sakit</th><th>Izin</th><th>Alpa</th><th>Hari tercatat</th><th>% hadir</th></tr>
        <tr>
            <td class="c">{{ $s['kehadiran']['H'] }}</td><td class="c">{{ $s['kehadiran']['T'] }}</td><td class="c">{{ $s['kehadiran']['S'] }}</td>
            <td class="c">{{ $s['kehadiran']['I'] }}</td><td class="c">{{ $s['kehadiran']['A'] }}</td><td class="c">{{ $s['kehadiran']['total'] }}</td>
            <td class="c">{{ $s['kehadiran']['persen'] === null ? '-' : $s['kehadiran']['persen'].'%' }}</td>
        </tr>
    </table>
@endif

<p class="judul">{{ $s['format']['tampil_kehadiran'] ? 'C' : 'B' }}. Catatan Wali Kelas</p>
<div class="catatan">{{ $s['catatan'] ?: '-' }}</div>

@php($tgl = \Carbon\Carbon::parse($s['pengesahan']['pada'])->locale('id')->translatedFormat('d F Y'))
<table class="ttd" style="margin-top:8mm">
    <tr>
        <td>Wali kelas<div class="garis">{{ $s['kelas']['wali'] ?: '.....................' }}</div>@if ($s['kelas']['wali_nip']) <div class="kecil">NIP {{ $s['kelas']['wali_nip'] }}</div> @endif</td>
        <td>{{ $s['format']['kota'] ? $s['format']['kota'].', ' : '' }}{{ $tgl }}<br>Kepala sekolah<div class="garis">{{ $s['kepala']['nama'] ?: '.....................' }}</div>@if ($s['kepala']['nip']) <div class="kecil">NIP {{ $s['kepala']['nip'] }}</div> @endif</td>
    </tr>
</table>

<p class="kecil" style="margin-top:8mm">
    Versi {{ $versi }}{{ $versi > 1 && $alasanRevisi ? ' (revisi: '.$alasanRevisi.')' : '' }}
    &nbsp;|&nbsp; Disahkan oleh {{ $s['pengesahan']['oleh'] }}
    &nbsp;|&nbsp; Kode verifikasi: {{ $kode }}
    @if ($tidakBerlaku) <br><strong>Versi ini sudah digantikan oleh versi {{ $versiBerlaku }}.</strong> @endif
    @if (! $utuh) <br><strong>PERINGATAN: isi salinan ini tidak cocok dengan sidiknya (kemungkinan diubah di luar sistem).</strong> @endif
</p>
</body>
</html>
