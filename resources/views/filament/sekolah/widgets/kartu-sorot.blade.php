<x-filament-widgets::widget>
    <div class="dash-sorot-grid" data-testid="kartu-sorot">
        <div class="dash-sorot dash-sorot-oranye">
            <span class="dash-sorot-angka">{{ \App\Modules\Laporan\DasborSekolah::siswaAktif() }}</span>
            <div><div class="dash-sorot-judul">Siswa aktif</div><div class="dash-sorot-ket">Di {{ \App\Modules\Laporan\DasborSekolah::jumlahKelas() }} kelas</div></div>
        </div>
        <div class="dash-sorot dash-sorot-ungu">
            <span class="dash-sorot-angka">{{ \App\Models\Announcement::where('status', 'terbit')->count() }}</span>
            <div><div class="dash-sorot-judul">Pengumuman terbit</div><div class="dash-sorot-ket">Dibaca orang tua dan siswa</div></div>
        </div>
    </div>
</x-filament-widgets::widget>
