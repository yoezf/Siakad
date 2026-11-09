<x-filament-panels::page>
    {{-- Gaya sebaris: halaman khusus ini tidak memakai tema Tailwind buatan sendiri. --}}
    @php($siswa = $this->daftarSiswa())
    <div class="ui-bar" >
        <select class="ui-select" wire:model.live="jenis" aria-label="Jenis kehadiran" >
            <option value="harian">Harian</option>
            <option value="pelajaran">Per pelajaran</option>
        </select>
        <select class="ui-select" wire:model.live="kelasId" aria-label="Kelas" style="min-width:9rem">
            @foreach ($this->opsiKelas() as $id => $nama)
                <option value="{{ $id }}">{{ $nama }}</option>
            @endforeach
        </select>
        <input class="ui-input" type="date" wire:model.live="tanggal" max="{{ today()->toDateString() }}" aria-label="Tanggal" >
        @if ($jenis === 'pelajaran')
            <select class="ui-select" wire:model.live="entriId" aria-label="Pelajaran" style="min-width:14rem">
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
        <div class="ui-wrap" >
            <table class="ui-table" data-testid="presensi-siswa" style="min-width:36rem">
                <thead>
                    <tr>
                        <th >Siswa</th>
                        <th >Status</th>
                        <th >Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($siswa as $s)
                        <tr>
                            <td >{{ $s->name }}</td>
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
</x-filament-panels::page>
