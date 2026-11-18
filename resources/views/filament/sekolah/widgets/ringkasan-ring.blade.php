<x-filament-widgets::widget>
    @php
        $s = \App\Modules\Laporan\DasborSekolah::kehadiranSiswa();
        $g = \App\Modules\Laporan\DasborSekolah::kehadiranGuru();
        $r = \App\Modules\Laporan\DasborSekolah::raporDisahkan();
        $kartu = [
            ['judul' => 'Kehadiran siswa', 'nilai' => $s['persen'], 'ket' => $s['tanggal'] ? 'Tanggal '.$s['tanggal']->format('d-m-Y') : 'Belum ada data', 'warna' => '#6366f1'],
            ['judul' => 'Kehadiran guru', 'nilai' => $g['persen'], 'ket' => $g['tanggal'] ? 'Tanggal '.$g['tanggal']->format('d-m-Y') : 'Belum ada data', 'warna' => '#f59e0b'],
            ['judul' => 'Rapor disahkan', 'nilai' => $r['persen'], 'ket' => $r['jumlah'].' dari '.$r['total'].' siswa', 'warna' => '#f97316'],
        ];
        $keliling = 2 * M_PI * 34;
    @endphp
    <div class="dash-ring-grid" data-testid="ringkasan-ring">
        @foreach ($kartu as $k)
            <div class="dash-card dash-ring">
                <div>
                    <div class="dash-label">{{ $k['judul'] }}</div>
                    <div class="dash-angka">{{ $k['nilai'] === null ? '-' : $k['nilai'].'%' }}</div>
                    <div class="dash-ket">{{ $k['ket'] }}</div>
                </div>
                <svg viewBox="0 0 80 80" class="dash-ring-svg" role="img" aria-label="{{ $k['judul'] }} {{ $k['nilai'] ?? 0 }} persen">
                    <circle cx="40" cy="40" r="34" class="dash-ring-latar"/>
                    <circle cx="40" cy="40" r="34" fill="none" stroke="{{ $k['warna'] }}" stroke-width="7" stroke-linecap="round" transform="rotate(-90 40 40)"
                        stroke-dasharray="{{ round($keliling, 2) }}" stroke-dashoffset="{{ round($keliling * (1 - ($k['nilai'] ?? 0) / 100), 2) }}"/>
                    <text x="40" y="45" text-anchor="middle" class="dash-ring-teks">{{ $k['nilai'] === null ? '-' : $k['nilai'].'%' }}</text>
                </svg>
            </div>
        @endforeach
    </div>
</x-filament-widgets::widget>
