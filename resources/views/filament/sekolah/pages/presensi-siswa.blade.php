<x-filament-panels::page>
    {{-- Gaya sebaris: halaman khusus ini tidak memakai tema Tailwind buatan sendiri. --}}
    @php($siswa = $this->daftarSiswa())
    <div style="display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; margin-bottom:1rem">
        <select wire:model.live="jenis" aria-label="Jenis kehadiran" style="padding:.5rem .75rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit">
            <option value="harian">Harian</option>
            <option value="pelajaran">Per pelajaran</option>
        </select>
        <select wire:model.live="kelasId" aria-label="Kelas" style="padding:.5rem .75rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit; min-width:9rem">
            @foreach ($this->opsiKelas() as $id => $nama)
                <option value="{{ $id }}">{{ $nama }}</option>
            @endforeach
        </select>
        <input type="date" wire:model.live="tanggal" max="{{ today()->toDateString() }}" aria-label="Tanggal" style="padding:.5rem .75rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit">
        @if ($jenis === 'pelajaran')
            <select wire:model.live="entriId" aria-label="Pelajaran" style="padding:.5rem .75rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit; min-width:14rem">
                <option value="">Pilih pelajaran</option>
                @foreach ($this->opsiEntri() as $e)
                    <option value="{{ $e->id }}">{{ $e->timeSlot->name }}: {{ $e->subject->name }}</option>
                @endforeach
            </select>
        @endif
    </div>

    @if ($siswa->isEmpty())
        <p>
            @if ($jenis === 'pelajaran' && ! $entriId)
                Pilih pelajaran yang dijadwalkan pada tanggal ini.
            @else
                Tidak ada siswa yang bisa dicatat. Pastikan Anda berhak atas kelas ini dan ada siswa yang terdaftar pada tanggal tersebut.
            @endif
        </p>
    @else
        <div style="overflow-x:auto">
            <table style="border-collapse:collapse; width:100%; min-width:36rem" data-testid="presensi-siswa">
                <thead>
                    <tr>
                        <th style="border:1px solid #9ca3af; padding:.5rem; text-align:left">Siswa</th>
                        <th style="border:1px solid #9ca3af; padding:.5rem">Status</th>
                        <th style="border:1px solid #9ca3af; padding:.5rem; text-align:left">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($siswa as $s)
                        <tr>
                            <td style="border:1px solid #9ca3af; padding:.5rem">{{ $s->name }}</td>
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
</x-filament-panels::page>
