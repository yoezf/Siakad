<x-filament-panels::page>
    {{-- Gaya sebaris: halaman khusus ini tidak memakai tema Tailwind buatan sendiri. --}}
    @php($komponen = $this->komponen())
    @php($baris = $this->baris())
    <div style="display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; margin-bottom:1rem">
        <select wire:model.live="semesterId" aria-label="Semester" style="padding:.5rem .75rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit; min-width:12rem">
            @foreach ($this->opsiSemester() as $id => $nama)
                <option value="{{ $id }}">{{ $nama }}</option>
            @endforeach
        </select>
        <select wire:model.live="kelasId" aria-label="Kelas" style="padding:.5rem .75rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit; min-width:8rem">
            @foreach ($this->opsiKelas() as $id => $nama)
                <option value="{{ $id }}">{{ $nama }}</option>
            @endforeach
        </select>
        <select wire:model.live="mapelId" aria-label="Mata pelajaran" style="padding:.5rem .75rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit; min-width:12rem">
            @foreach ($this->opsiMapel() as $id => $nama)
                <option value="{{ $id }}">{{ $nama }}</option>
            @endforeach
        </select>
    </div>

    @if ($komponen->isEmpty())
        <p>Belum ada komponen penilaian aktif. Atur dulu di menu <strong>Komponen penilaian</strong>.</p>
    @elseif (count($baris) === 0)
        <p>Tidak ada siswa untuk dinilai. Pastikan Anda berhak menilai mata pelajaran ini di kelas tersebut dan ada siswa yang terdaftar.</p>
    @else
        <p style="opacity:.8; margin-bottom:.75rem"><small>Isi nilai 0 sampai 100 (boleh koma, mis. 85,5). Kolom kosong berarti belum dinilai, bukan nol. Nilai akhir muncul setelah semua komponen terisi dan disimpan.</small></p>
        <div style="overflow-x:auto">
            <table style="border-collapse:collapse; width:100%; min-width:40rem" data-testid="input-nilai">
                <thead>
                    <tr>
                        <th style="border:1px solid #9ca3af; padding:.5rem; text-align:left">Siswa</th>
                        @foreach ($komponen as $k)
                            <th style="border:1px solid #9ca3af; padding:.5rem">{{ $k->name }}<br><small>({{ $k->weight }}%)</small></th>
                        @endforeach
                        <th style="border:1px solid #9ca3af; padding:.5rem">Nilai akhir</th>
                        <th style="border:1px solid #9ca3af; padding:.5rem">Predikat</th>
                        <th style="border:1px solid #9ca3af; padding:.5rem">Tuntas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($baris as $r)
                        <tr>
                            <td style="border:1px solid #9ca3af; padding:.5rem">{{ $r['student']->name }}</td>
                            @foreach ($komponen as $k)
                                <td style="border:1px solid #9ca3af; padding:.35rem; text-align:center">
                                    <input type="text" inputmode="decimal" wire:model="nilai.{{ $r['student']->id }}.{{ $k->id }}" aria-label="{{ $k->name }} {{ $r['student']->name }}" style="width:5rem; text-align:center; padding:.35rem .5rem; border-radius:.4rem; border:1px solid #9ca3af; background:transparent; color:inherit">
                                </td>
                            @endforeach
                            <td style="border:1px solid #9ca3af; padding:.5rem; text-align:center"><strong>{{ $r['bulat'] ?? '-' }}</strong></td>
                            <td style="border:1px solid #9ca3af; padding:.5rem; text-align:center">{{ $r['predikat'] ?? '-' }}</td>
                            <td style="border:1px solid #9ca3af; padding:.5rem; text-align:center">{{ $r['tuntas'] === null ? '-' : ($r['tuntas'] ? 'Ya' : 'Belum') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="margin-top:1rem">
            <button type="button" wire:click="simpan" style="padding:.5rem 1rem; border-radius:.5rem; border:0; background:#0891b2; color:#fff; font-weight:600">Simpan nilai</button>
        </div>
    @endif
</x-filament-panels::page>
