<x-filament-panels::page>
    <script src="{{ asset('vendor/face-api/face-api.js') }}"></script>
    <script src="{{ asset('js/wajah.js') }}?v={{ filemtime(public_path('js/wajah.js')) }}"></script>

    <div x-data="wajahApp('daftar')" data-testid="daftar-wajah">
        <div class="ui-card">
            <h3 class="ui-title">Daftar wajah siswa</h3>
            <p class="ui-sub" style="margin-bottom:1rem">Wajah diambil lewat kamera perangkat ini sebanyak 5 sampel. Yang disimpan hanya data numerik terenkripsi, bukan foto. Data bisa dihapus kapan saja.</p>
            <div class="ui-form">
                <label>Kelas
                    <select class="ui-select" wire:model.live="kelasId" aria-label="Kelas" style="width:100%">
                        @foreach ($this->opsiKelas() as $id => $nama)<option value="{{ $id }}">{{ $nama }}</option>@endforeach
                    </select>
                </label>
            </div>
            <div wire:ignore style="margin-top:1rem" x-show="kamera" x-cloak><video x-ref="video" class="ui-video" playsinline muted autoplay></video></div>
            <p class="ui-sub" x-text="pesan" x-bind:style="galat ? 'color:#b42318;font-weight:600' : ''" data-testid="pesan-wajah" style="margin-top:.75rem"></p>
            <div class="ui-bar" style="margin-top:.5rem"><button type="button" class="ui-btn" x-show="kamera" x-on:click="matikan()" x-cloak>Matikan kamera</button></div>
        </div>

        @php($terdaftar = $this->terdaftar())
        <div class="ui-card">
            <div class="ui-wrap" style="margin:0; box-shadow:none">
                <table class="ui-table" data-testid="tabel-wajah">
                    <thead><tr><th>Siswa</th><th>Data wajah</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($this->daftarSiswa() as $s)
                            <tr>
                                <td>{{ $s->name }}</td>
                                <td>{{ in_array($s->id, $terdaftar, true) ? 'Terdaftar' : 'Belum' }}</td>
                                <td style="white-space:nowrap">
                                    <button type="button" class="ui-btn" x-on:click="daftar({{ $s->id }}, @js($s->name))" x-bind:disabled="sibuk || memuat">{{ in_array($s->id, $terdaftar, true) ? 'Ambil ulang' : 'Ambil wajah' }}</button>
                                    @if (in_array($s->id, $terdaftar, true))
                                        <button type="button" class="ui-btn" wire:click="hapusWajah({{ $s->id }})" wire:confirm="Hapus data wajah {{ $s->name }}?">Hapus</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="ui-sub">Belum ada siswa di kelas ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
