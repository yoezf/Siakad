<x-filament-panels::page>
    {{-- Gaya sebaris: halaman khusus ini tidak memakai tema Tailwind buatan sendiri. --}}
    @php($t = $this->tabel())
    @php($sel = 'padding:.5rem .75rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit')
    <div style="display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; margin-bottom:1rem">
        <select wire:model.live="jenis" aria-label="Jenis laporan" style="{{ $sel }}">
            @foreach (\App\Modules\Laporan\Laporan::JENIS as $k => $nama)<option value="{{ $k }}">{{ $nama }}</option>@endforeach
        </select>
        <select wire:model.live="semesterId" aria-label="Semester" style="{{ $sel }}; min-width:12rem">
            @foreach ($this->opsiSemester() as $id => $nama)<option value="{{ $id }}">{{ $nama }}</option>@endforeach
        </select>
        <select wire:model.live="kelasId" aria-label="Kelas" style="{{ $sel }}; min-width:8rem">
            @foreach ($this->opsiKelas() as $id => $nama)<option value="{{ $id }}">{{ $nama }}</option>@endforeach
        </select>
        @if ($jenis === 'kehadiran')
            <select wire:model.live="periode" aria-label="Periode" style="{{ $sel }}">
                <option value="semester">Satu semester</option>
                <option value="bulan">Per bulan</option>
            </select>
            @if ($periode === 'bulan')
                <input type="month" wire:model.live="bulan" aria-label="Bulan" style="{{ $sel }}">
            @endif
        @endif
    </div>

    @if (! $t)
        <p data-testid="laporan-kosong">Pilih semester dan kelas yang berhak Anda lihat.</p>
    @else
        <div style="display:flex; gap:.75rem; margin-bottom:1rem">
            <button type="button" wire:click="unduh('pdf')" style="padding:.5rem 1rem; border-radius:.5rem; border:0; background:#0891b2; color:#fff; font-weight:600">Unduh PDF</button>
            <button type="button" wire:click="unduh('xlsx')" style="padding:.5rem 1rem; border-radius:.5rem; border:1px solid #0891b2; background:transparent; color:inherit; font-weight:600">Unduh Excel</button>
        </div>
        <h3 style="font-weight:700">{{ $t['judul'] }}</h3>
        @foreach ($t['keterangan'] as $k)<p style="opacity:.85">{{ $k }}</p>@endforeach
        <div style="overflow-x:auto; margin-top:.75rem">
            <table style="border-collapse:collapse; width:100%; min-width:32rem" data-testid="laporan-tabel">
                <thead><tr>@foreach ($t['kolom'] as $kolom)<th style="border:1px solid #9ca3af; padding:.4rem">{{ $kolom }}</th>@endforeach</tr></thead>
                <tbody>
                    @forelse ($t['baris'] as $baris)
                        <tr>@foreach ($baris as $i => $c)<td style="border:1px solid #9ca3af; padding:.4rem; {{ $i === 1 ? '' : 'text-align:center' }}">{{ is_float($c) ? number_format($c, 2, ',', '') : ($c ?? '-') }}</td>@endforeach</tr>
                    @empty
                        <tr><td colspan="{{ count($t['kolom']) }}" style="border:1px solid #9ca3af; padding:.4rem; text-align:center">Tidak ada siswa.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
