<x-filament-panels::page>
    {{-- Gaya sebaris: halaman khusus ini tidak memakai tema Tailwind buatan sendiri. --}}
    @php($baris = $this->baris())
    <div style="display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; margin-bottom:1rem">
        <select wire:model.live="kelasId" aria-label="Kelas" style="padding:.5rem .75rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit; min-width:9rem">
            @foreach ($this->opsiKelas() as $id => $nama)
                <option value="{{ $id }}">{{ $nama }}</option>
            @endforeach
        </select>
        <select wire:model.live="periode" aria-label="Periode" style="padding:.5rem .75rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit">
            <option value="hari">Per hari</option>
            <option value="bulan">Per bulan</option>
            <option value="semester">Per semester</option>
        </select>
        @if ($periode === 'hari')
            <input type="date" wire:model.live="tanggal" aria-label="Tanggal" style="padding:.5rem .75rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit">
        @elseif ($periode === 'bulan')
            <input type="month" wire:model.live="bulan" aria-label="Bulan" style="padding:.5rem .75rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit">
        @else
            <select wire:model.live="semesterId" aria-label="Semester" style="padding:.5rem .75rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit; min-width:12rem">
                @foreach ($this->opsiSemester() as $id => $nama)
                    <option value="{{ $id }}">{{ $nama }}</option>
                @endforeach
            </select>
        @endif
    </div>

    @if (count($baris) === 0)
        <p>Tidak ada data. Pilih kelas yang berhak Anda lihat dan periode yang memiliki siswa terdaftar.</p>
    @else
        <div style="overflow-x:auto">
            <table style="border-collapse:collapse; width:100%; min-width:36rem" data-testid="rekap-kehadiran">
                <thead>
                    <tr>
                        <th style="border:1px solid #9ca3af; padding:.5rem; text-align:left">Siswa</th>
                        @foreach (\App\Modules\Kehadiran\Status::LABEL as $label)
                            <th style="border:1px solid #9ca3af; padding:.5rem">{{ $label }}</th>
                        @endforeach
                        <th style="border:1px solid #9ca3af; padding:.5rem">Hari tercatat</th>
                        <th style="border:1px solid #9ca3af; padding:.5rem">% hadir</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($baris as $r)
                        <tr>
                            <td style="border:1px solid #9ca3af; padding:.5rem">{{ $r['student']->name }}</td>
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
        <p style="margin-top:.75rem; opacity:.75"><small>Persentase = (hadir + terlambat) dibagi hari yang tercatat. Hanya kehadiran harian yang dihitung.</small></p>
    @endif
</x-filament-panels::page>
