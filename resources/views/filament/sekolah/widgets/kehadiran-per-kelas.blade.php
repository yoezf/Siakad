<x-filament-widgets::widget>
    <div class="dash-card" data-testid="kehadiran-per-kelas">
        <h3 class="dash-judul">Kehadiran per kelas</h3>
        @forelse (\App\Modules\Laporan\DasborSekolah::perKelas() as $k)
            <div class="dash-bar-baris">
                <span class="dash-bar-nama">{{ $k['kelas'] }}</span>
                <span class="dash-bar-trek"><span class="dash-bar-isi" style="width: {{ $k['persen'] }}%"></span></span>
                <span class="dash-bar-persen">{{ $k['persen'] }}%</span>
            </div>
        @empty
            <p class="ui-sub">Belum ada kehadiran yang tercatat.</p>
        @endforelse
    </div>
</x-filament-widgets::widget>
