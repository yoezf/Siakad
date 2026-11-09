<x-filament-panels::page>
    {{-- Gaya sebaris: halaman khusus ini tidak memakai tema Tailwind buatan sendiri. --}}
    @php($baris = $this->baris())
    <div class="ui-bar" >
        <select class="ui-select" wire:model.live="kelasId" aria-label="Kelas" style="min-width:9rem">
            @foreach ($this->opsiKelas() as $id => $nama)
                <option value="{{ $id }}">{{ $nama }}</option>
            @endforeach
        </select>
        <select class="ui-select" wire:model.live="periode" aria-label="Periode" >
            <option value="hari">Per hari</option>
            <option value="bulan">Per bulan</option>
            <option value="semester">Per semester</option>
        </select>
        @if ($periode === 'hari')
            <input class="ui-input" type="date" wire:model.live="tanggal" aria-label="Tanggal" >
        @elseif ($periode === 'bulan')
            <input class="ui-input" type="month" wire:model.live="bulan" aria-label="Bulan" >
        @else
            <select class="ui-select" wire:model.live="semesterId" aria-label="Semester" style="min-width:12rem">
                @foreach ($this->opsiSemester() as $id => $nama)
                    <option value="{{ $id }}">{{ $nama }}</option>
                @endforeach
            </select>
        @endif
    </div>

    @if (count($baris) === 0)
        <p>Tidak ada data. Pilih kelas yang berhak Anda lihat dan periode yang memiliki siswa terdaftar.</p>
    @else
        <div class="ui-wrap" >
            <table class="ui-table" data-testid="rekap-kehadiran" style="min-width:36rem">
                <thead>
                    <tr>
                        <th >Siswa</th>
                        @foreach (\App\Modules\Kehadiran\Status::LABEL as $label)
                            <th >{{ $label }}</th>
                        @endforeach
                        <th >Hari tercatat</th>
                        <th >% hadir</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($baris as $r)
                        <tr>
                            <td >{{ $r['student']->name }}</td>
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
        <p class="ui-muted" style="margin-top:.75rem"><small>Persentase = (hadir + terlambat) dibagi hari yang tercatat. Hanya kehadiran harian yang dihitung.</small></p>
    @endif
</x-filament-panels::page>
