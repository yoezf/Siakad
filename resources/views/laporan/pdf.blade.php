<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $t['judul'] }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
    h1 { font-size: 14px; margin: 0 0 2px 0; }
    h2 { font-size: 12px; margin: 0 0 6px 0; font-weight: normal; }
    p { margin: 1px 0; }
    table { border-collapse: collapse; width: 100%; margin-top: 8px; }
    th, td { border: 1px solid #444; padding: 3px 4px; }
    th { background: #e5e7eb; }
    .tengah { text-align: center; }
    .kecil { font-size: 8px; color: #555; margin-top: 8px; }
</style>
</head>
<body>
    <h1>{{ $t['sekolah'] }}</h1>
    <h2>{{ $t['judul'] }}</h2>
    @foreach ($t['keterangan'] as $k)<p>{{ $k }}</p>@endforeach
    <table>
        <thead><tr>@foreach ($t['kolom'] as $kolom)<th>{{ $kolom }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse ($t['baris'] as $baris)
                <tr>
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
