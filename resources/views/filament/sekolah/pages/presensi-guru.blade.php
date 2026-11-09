<x-filament-panels::page>
    {{-- Gaya sebaris: halaman khusus ini tidak memakai tema Tailwind buatan sendiri. --}}
    @if ($this->bisaMencatat())
        <h3 style="font-weight:600; margin-bottom:.5rem">Catat kehadiran</h3>
        <div style="margin-bottom:1rem">
            <input type="date" wire:model.live="tanggal" max="{{ today()->toDateString() }}" aria-label="Tanggal" style="padding:.5rem .75rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit">
        </div>
        <div style="overflow-x:auto">
            <table style="border-collapse:collapse; width:100%; min-width:36rem" data-testid="presensi-guru">
                <thead>
                    <tr>
                        <th style="border:1px solid #9ca3af; padding:.5rem; text-align:left">Pegawai</th>
                        <th style="border:1px solid #9ca3af; padding:.5rem">Status</th>
                        <th style="border:1px solid #9ca3af; padding:.5rem; text-align:left">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->daftarPegawai() as $s)
                        <tr>
                            <td style="border:1px solid #9ca3af; padding:.5rem">{{ $s->name }} <small style="opacity:.7">({{ \App\Models\Staff::KIND[$s->kind] ?? $s->kind }})</small></td>
                            <td style="border:1px solid #9ca3af; padding:.5rem; text-align:center">
                                <select wire:model="status.{{ $s->id }}" aria-label="Status {{ $s->name }}" style="padding:.35rem .5rem; border-radius:.4rem; border:1px solid #9ca3af; background:transparent; color:inherit">
                                    @foreach (\App\Modules\Kehadiran\Status::LABEL as $kode => $label)
                                        <option value="{{ $kode }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td style="border:1px solid #9ca3af; padding:.5rem">
                                <input type="text" wire:model="catatan.{{ $s->id }}" maxlength="255" aria-label="Catatan {{ $s->name }}" style="width:100%; padding:.35rem .5rem; border-radius:.4rem; border:1px solid #9ca3af; background:transparent; color:inherit">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="display:flex; gap:.75rem; margin-top:1rem">
            <button type="button" wire:click="semuaHadir" style="padding:.5rem 1rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit">Tandai semua hadir</button>
            <button type="button" wire:click="simpan" style="padding:.5rem 1rem; border-radius:.5rem; border:0; background:#0891b2; color:#fff; font-weight:600">Simpan kehadiran</button>
        </div>
    @endif

    <h3 style="font-weight:600; margin:1.5rem 0 .5rem">Rekap bulanan</h3>
    <div style="margin-bottom:1rem">
        <input type="month" wire:model.live="bulan" aria-label="Bulan" style="padding:.5rem .75rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit">
    </div>
    <div style="overflow-x:auto">
        <table style="border-collapse:collapse; width:100%; min-width:36rem" data-testid="rekap-guru">
            <thead>
                <tr>
                    <th style="border:1px solid #9ca3af; padding:.5rem; text-align:left">Pegawai</th>
                    @foreach (\App\Modules\Kehadiran\Status::LABEL as $label)
                        <th style="border:1px solid #9ca3af; padding:.5rem">{{ $label }}</th>
                    @endforeach
                    <th style="border:1px solid #9ca3af; padding:.5rem">Hari tercatat</th>
                    <th style="border:1px solid #9ca3af; padding:.5rem">% hadir</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->rekap() as $r)
                    <tr>
                        <td style="border:1px solid #9ca3af; padding:.5rem">{{ $r['staff']->name }}</td>
                        @foreach (array_keys(\App\Modules\Kehadiran\Status::LABEL) as $kode)
                            <td style="border:1px solid #9ca3af; padding:.5rem; text-align:center">{{ $r[$kode] }}</td>
                        @endforeach
                        <td style="border:1px solid #9ca3af; padding:.5rem; text-align:center">{{ $r['total'] }}</td>
                        <td style="border:1px solid #9ca3af; padding:.5rem; text-align:center">{{ $r['persen'] === null ? '-' : $r['persen'].'%' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
