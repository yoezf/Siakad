<x-filament-panels::page>
    @php($d = $this->data())
    <div class="ui-card" data-testid="statistik-ai">
        <div class="ui-kv">
            <div><b>{{ $d['total'] }}</b><span>Total analisis</span></div>
            <div><b>{{ $d['jenis']['palmistry'] }}</b><span>Palmistry</span></div>
            <div><b>{{ $d['jenis']['physiognomy'] }}</b><span>Physiognomy</span></div>
            <div><b>{{ $d['jenis']['custom'] }}</b><span>Prompt kustom</span></div>
            <div><b>{{ $d['berhasil'] === null ? '-' : $d['berhasil'].'%' }}</b><span>Berhasil</span></div>
            <div><b>{{ $d['hariIni'] }} / {{ $d['batas'] }}</b><span>Pemakaian hari ini</span></div>
        </div>
    </div>

    <div class="ui-card">
        <h3 class="ui-title">Menurut status dan keterbacaan gambar</h3>
        <div class="ui-kv">
            @foreach (\App\Models\AiAnalysis::STATUS as $k => $l)<div><b>{{ $d['status'][$k] }}</b><span>{{ $l }}</span></div>@endforeach
            @foreach (\App\Modules\Ai\Skema::KETERBACAAN as $k => $l)<div><b>{{ $d['keterbacaan'][$k] }}</b><span>Keterbacaan {{ strtolower($l) }}</span></div>@endforeach
        </div>
    </div>

    <div class="ui-card">
        <h3 class="ui-title">14 hari terakhir</h3>
        @php($maks = max(1, max($d['harian'])))
        <div class="ui-bars" data-testid="harian-ai">
            @foreach ($d['harian'] as $tgl => $n)
                <div class="ui-bar-baris"><span class="ui-sub">{{ \Illuminate\Support\Carbon::parse($tgl)->format('d-m') }}</span>
                    <span class="ui-bar-isi"><i style="width:{{ $n / $maks * 100 }}%"></i></span><b>{{ $n }}</b></div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
