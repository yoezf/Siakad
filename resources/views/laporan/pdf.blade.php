<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $t['judul'] }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1b2433; }
    .kop { border-bottom: 2px solid #4f46e5; padding-bottom: 5px; margin-bottom: 6px; }
    h1 { font-size: 15px; margin: 0 0 2px 0; color: #3730a3; }
    h2 { font-size: 12px; margin: 0 0 6px 0; font-weight: normal; color: #3b4254; }
    p { margin: 1px 0; }
    table { border-collapse: collapse; width: 100%; margin-top: 8px; }
    th, td { border: 1px solid #cdd1ea; padding: 3px 4px; }
    th { background: #4f46e5; color: #ffffff; }
    tr.genap td { background: #f6f7ff; }
    .tengah { text-align: center; }
    .kecil { font-size: 8px; color: #5b6475; margin-top: 8px; }
</style>
</head>
<body>
    <div class="kop">
        <h1>{{ $t['sekolah'] }}</h1>
        <h2>{{ $t['judul'] }}</h2>
        @foreach ($t['keterangan'] as $k)<p>{{ $k }}</p>@endforeach
    </div>
    <table>
        <thead><tr>@foreach ($t['kolom'] as $kolom)<th>{{ $kolom }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse ($t['baris'] as $n => $baris)
                <tr @class(['genap' => $n % 2 === 1])>
                    @foreach ($baris as $i => $sel)
                        <td @class(['tengah' => $i !== 1])>{{ is_float($sel) ? number_format($sel, 2, ',', '') : ($sel ?? '-') }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($t['kolom']) }}" class="tengah">Tidak ada siswa.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="kecil">Dicetak {{ now()->format('d-m-Y H:i') }}.</p>
</body>
</html>
