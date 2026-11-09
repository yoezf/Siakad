<x-filament-panels::page>
    {{-- Gaya sebaris: halaman khusus ini tidak memakai tema Tailwind buatan sendiri. --}}
    @php($t = $this->tabel())
    <div class="ui-bar" >
        <select class="ui-select" wire:model.live="jenis" aria-label="Jenis laporan" >
            @foreach (\App\Modules\Laporan\Laporan::JENIS as $k => $nama)<option value="{{ $k }}">{{ $nama }}</option>@endforeach
        </select>
        <select class="ui-select" wire:model.live="semesterId" aria-label="Semester" style="min-width:12rem">
            @foreach ($this->opsiSemester() as $id => $nama)<option value="{{ $id }}">{{ $nama }}</option>@endforeach
        </select>
        <select class="ui-select" wire:model.live="kelasId" aria-label="Kelas" style="min-width:8rem">
            @foreach ($this->opsiKelas() as $id => $nama)<option value="{{ $id }}">{{ $nama }}</option>@endforeach
        </select>
        @if ($jenis === 'kehadiran')
            <select class="ui-select" wire:model.live="periode" aria-label="Periode" >
                <option value="semester">Satu semester</option>
                <option value="bulan">Per bulan</option>
            </select>
            @if ($periode === 'bulan')
                <input class="ui-select" type="month" wire:model.live="bulan" aria-label="Bulan" >
            @endif
        @endif
    </div>

    @if (! $t)
        <p data-testid="laporan-kosong">Pilih semester dan kelas yang berhak Anda lihat.</p>
    @else
        <div class="ui-bar" >
            <button class="ui-btn ui-btn-primary" type="button" wire:click="unduh('pdf')" >Unduh PDF</button>
            <button type="button" wire:click="unduh('xlsx')" class="ui-btn">Unduh Excel</button>
        </div>
        <h3 style="font-weight:700">{{ $t['judul'] }}</h3>
        @foreach ($t['keterangan'] as $k)<p class="ui-muted" >{{ $k }}</p>@endforeach
        <div style="overflow-x:auto; margin-top:.75rem">
            <table class="ui-table" data-testid="laporan-tabel" style="min-width:32rem">
                <thead><tr>@foreach ($t['kolom'] as $kolom)<th >{{ $kolom }}</th>@endforeach</tr></thead>
                <tbody>
                    @forelse ($t['baris'] as $baris)
                        <tr>@foreach ($baris as $i => $c)<td style="{{ $i === 1 ? '' : 'text-align:center' }}">{{ is_float($c) ? number_format($c, 2, ',', '') : ($c ?? '-') }}</td>@endforeach</tr>
                    @empty
                        <tr><td class="c" colspan="{{ count($t['kolom']) }}" >Tidak ada siswa.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
