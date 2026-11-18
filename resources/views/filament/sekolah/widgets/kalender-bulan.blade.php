<x-filament-widgets::widget>
    @php
        $bulan = now()->startOfMonth();
        $tercatat = \App\Modules\Laporan\DasborSekolah::tanggalTercatat($bulan);
        $kosong = $bulan->dayOfWeekIso - 1; // Senin = kolom pertama
    @endphp
    <div class="dash-card" data-testid="kalender-bulan">
        <h3 class="dash-judul">{{ $bulan->locale('id')->translatedFormat('F Y') }}</h3>
        <div class="dash-kal">
            @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $h)<span class="dash-kal-hari">{{ $h }}</span>@endforeach
            @for ($i = 0; $i < $kosong; $i++)<span></span>@endfor
            @for ($d = 1; $d <= $bulan->daysInMonth; $d++)
                @php($tgl = $bulan->copy()->day($d)->toDateString())
                <span class="dash-kal-tgl {{ $tgl === today()->toDateString() ? 'is-hari-ini' : '' }} {{ in_array($tgl, $tercatat, true) ? 'is-tercatat' : '' }}">{{ $d }}</span>
            @endfor
        </div>
        <p class="ui-sub" style="margin-top:.6rem">Tanggal berwarna: kehadiran sudah dicatat.</p>
    </div>
</x-filament-widgets::widget>
