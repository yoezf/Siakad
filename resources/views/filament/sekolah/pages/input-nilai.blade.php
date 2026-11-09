<x-filament-panels::page>
    {{-- Gaya sebaris: halaman khusus ini tidak memakai tema Tailwind buatan sendiri. --}}
    @php($komponen = $this->komponen())
    @php($baris = $this->baris())
    <div class="ui-bar" >
        <select class="ui-select" wire:model.live="semesterId" aria-label="Semester" style="min-width:12rem">
            @foreach ($this->opsiSemester() as $id => $nama)
                <option value="{{ $id }}">{{ $nama }}</option>
            @endforeach
        </select>
        <select class="ui-select" wire:model.live="kelasId" aria-label="Kelas" style="min-width:8rem">
            @foreach ($this->opsiKelas() as $id => $nama)
                <option value="{{ $id }}">{{ $nama }}</option>
            @endforeach
        </select>
        <select class="ui-select" wire:model.live="mapelId" aria-label="Mata pelajaran" style="min-width:12rem">
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
        <p class="ui-muted" style="margin-bottom:.75rem"><small>Isi nilai 0 sampai 100 (boleh koma, mis. 85,5). Kolom kosong berarti belum dinilai, bukan nol. Nilai akhir muncul setelah semua komponen terisi dan disimpan.</small></p>
        <div class="ui-wrap" >
            <table class="ui-table" data-testid="input-nilai" style="min-width:40rem">
                <thead>
                    <tr>
                        <th >Siswa</th>
                        @foreach ($komponen as $k)
                            <th >{{ $k->name }}<br><small>({{ $k->weight }}%)</small></th>
                        @endforeach
                        <th >Nilai akhir</th>
                        <th >Predikat</th>
                        <th >Tuntas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($baris as $r)
                        <tr>
                            <td >{{ $r['student']->name }}</td>
                            @foreach ($komponen as $k)
                                <td class="c" style="padding:.35rem">
                                    <input class="ui-input" type="text" inputmode="decimal" wire:model="nilai.{{ $r['student']->id }}.{{ $k->id }}" aria-label="{{ $k->name }} {{ $r['student']->name }}" style="text-align:center">
                                </td>
                            @endforeach
                            <td class="c" ><strong>{{ $r['bulat'] ?? '-' }}</strong></td>
                            <td class="c" >{{ $r['predikat'] ?? '-' }}</td>
                            <td class="c" >{{ $r['tuntas'] === null ? '-' : ($r['tuntas'] ? 'Ya' : 'Belum') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="margin-top:1rem">
            <button class="ui-btn ui-btn-primary" type="button" wire:click="simpan" >Simpan nilai</button>
        </div>
    @endif
</x-filament-panels::page>
