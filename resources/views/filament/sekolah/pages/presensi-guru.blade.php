<x-filament-panels::page>
    {{-- Gaya sebaris: halaman khusus ini tidak memakai tema Tailwind buatan sendiri. --}}
    @if ($this->bisaMencatat())
        <h3 style="font-weight:600; margin-bottom:.5rem">Catat kehadiran</h3>
        <div style="margin-bottom:1rem">
            <input class="ui-input" type="date" wire:model.live="tanggal" max="{{ today()->toDateString() }}" aria-label="Tanggal" >
        </div>
        <div class="ui-wrap" >
            <table class="ui-table" data-testid="presensi-guru" style="min-width:36rem">
                <thead>
                    <tr>
                        <th >Pegawai</th>
                        <th >Status</th>
                        <th >Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->daftarPegawai() as $s)
                        <tr>
                            <td >{{ $s->name }} <small class="ui-muted" >({{ \App\Models\Staff::KIND[$s->kind] ?? $s->kind }})</small></td>
                            <td class="c" >
                                <select class="ui-select" wire:model="status.{{ $s->id }}" aria-label="Status {{ $s->name }}" >
                                    @foreach (\App\Modules\Kehadiran\Status::LABEL as $kode => $label)
                                        <option value="{{ $kode }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td >
                                <input class="ui-input" type="text" wire:model="catatan.{{ $s->id }}" maxlength="255" aria-label="Catatan {{ $s->name }}" >
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="ui-bar" >
            <button class="ui-btn" type="button" wire:click="semuaHadir" >Tandai semua hadir</button>
            <button class="ui-btn ui-btn-primary" type="button" wire:click="simpan" >Simpan kehadiran</button>
        </div>
    @endif

    <h3 style="font-weight:600; margin:1.5rem 0 .5rem">Rekap bulanan</h3>
    <div style="margin-bottom:1rem">
        <input class="ui-input" type="month" wire:model.live="bulan" aria-label="Bulan" >
    </div>
    <div class="ui-wrap" >
        <table class="ui-table" data-testid="rekap-guru" style="min-width:36rem">
            <thead>
                <tr>
                    <th >Pegawai</th>
                    @foreach (\App\Modules\Kehadiran\Status::LABEL as $label)
                        <th >{{ $label }}</th>
                    @endforeach
                    <th >Hari tercatat</th>
                    <th >% hadir</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->rekap() as $r)
                    <tr>
                        <td >{{ $r['staff']->name }}</td>
                        @foreach (array_keys(\App\Modules\Kehadiran\Status::LABEL) as $kode)
                            <td class="c" >{{ $r[$kode] }}</td>
                        @endforeach
                        <td class="c" >{{ $r['total'] }}</td>
                        <td class="c" >{{ $r['persen'] === null ? '-' : $r['persen'].'%' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
