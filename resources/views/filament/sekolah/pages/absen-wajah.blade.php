<x-filament-panels::page>
    <script src="{{ asset('vendor/face-api/face-api.js') }}"></script>
    <script src="{{ asset('js/wajah.js') }}?v={{ filemtime(public_path('js/wajah.js')) }}"></script>

    <div class="ui-card" x-data="wajahApp('pindai')" data-testid="absen-wajah">
        <h3 class="ui-title">Absen dengan wajah</h3>
        <p class="ui-sub" style="margin-bottom:1rem">Pilih kelas, nyalakan kamera, lalu siswa menghadap kamera satu per satu dan berkedip. Pencocokan wajah terjadi di perangkat ini, foto tidak dikirim ke server. Siswa yang sudah punya status hari ini tidak diubah.</p>

        @if (empty($this->opsiKelas()))
            <div class="ui-alert ui-alert-info">Tidak ada kelas yang boleh Anda pindai.</div>
        @else
            <div class="ui-form">
                <label>Kelas
                    <select class="ui-select" wire:model="kelasId" aria-label="Kelas" style="width:100%" x-bind:disabled="aktif">
                        @foreach ($this->opsiKelas() as $id => $nama)<option value="{{ $id }}">{{ $nama }}</option>@endforeach
                    </select>
                </label>
            </div>
            <div class="ui-bar" style="margin:1rem 0">
                <button type="button" class="ui-btn ui-btn-primary" x-show="!aktif" x-on:click="mulaiPindai($wire.kelasId)" x-bind:disabled="memuat">Mulai pindai</button>
                <button type="button" class="ui-btn" x-show="aktif || kamera" x-on:click="matikan()" x-cloak>Matikan kamera</button>
            </div>

            <div wire:ignore x-show="kamera" x-cloak>
                <video x-ref="video" class="ui-video" playsinline muted autoplay></video>
            </div>
            <p class="ui-sub" x-text="pesan" x-bind:style="galat ? 'color:#b42318;font-weight:600' : ''" data-testid="pesan-wajah" style="margin-top:.75rem"></p>
            <p class="ui-sub" x-show="jumlahData" x-cloak>Siswa terdaftar wajahnya di kelas ini: <span x-text="jumlahData"></span></p>

            <div class="ui-wrap" style="margin:1rem 0 0; box-shadow:none" x-show="riwayat.length" x-cloak>
                <table class="ui-table">
                    <thead><tr><th>Waktu</th><th>Siswa</th><th>Hasil</th></tr></thead>
                    <tbody><template x-for="r in riwayat" x-bind:key="r.nama + r.waktu"><tr><td x-text="r.waktu"></td><td x-text="r.nama"></td><td x-text="r.teks"></td></tr></template></tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
