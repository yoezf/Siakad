<x-filament-panels::page>
    @include('filament.sekolah.ai.peringatan')

    @if ($this->bisaMembuat())
        <div class="ui-card" data-testid="formulir-ai">
            <h3 class="ui-title">{{ $this->jenis() === 'palmistry' ? 'Unggah foto telapak tangan' : 'Unggah foto wajah tampak depan' }}</h3>
            <p class="ui-sub" style="margin-bottom:1rem">
                {{ $this->jenis() === 'palmistry' ? 'Telapak tangan kanan atau kiri, terbuka, cahaya cukup, garis tidak tertutup bayangan.' : 'Satu wajah menghadap lurus ke kamera, cahaya merata, tanpa kacamata atau penutup wajah.' }}
                JPG, PNG, atau WEBP, maksimal 5 MB.
            </p>

            <div class="ui-form">
                <label>Nama orang yang difoto
                    <input type="text" class="ui-input" wire:model="subjek" maxlength="150" aria-label="Nama orang yang difoto">
                </label>
                <label>Nama pemberi persetujuan
                    <input type="text" class="ui-input" wire:model="pemberi" maxlength="150" aria-label="Nama pemberi persetujuan">
                </label>
                <label>Hubungan pemberi persetujuan
                    <select class="ui-select" wire:model="hubungan" aria-label="Hubungan pemberi persetujuan" style="width:100%">
                        @foreach (\App\Models\AiAnalysis::RELATION as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                    </select>
                </label>
                <label>Foto
                    <input type="file" class="ui-input" wire:model="foto" accept="image/jpeg,image/png,image/webp" aria-label="Foto">
                </label>
                <div wire:loading wire:target="foto" class="ui-sub">Mengunggah foto...</div>
            </div>

            <label class="ui-check"><input type="checkbox" wire:model="dewasa" aria-label="Orang yang difoto berusia 18 tahun ke atas"> Orang yang difoto berusia 18 tahun ke atas.</label>
            <label class="ui-check"><input type="checkbox" wire:model="setuju" aria-label="Persetujuan pemrosesan foto"> {{ $this->teksPersetujuan() }}</label>

            <div class="ui-bar" style="margin:1rem 0 0">
                <button type="button" class="ui-btn ui-btn-primary" wire:click="kirim" wire:loading.attr="disabled" wire:target="kirim,foto">Analisis foto</button>
            </div>
        </div>
    @endif

    @php($a = $this->analisis())
    @if ($a)
        @include('filament.sekolah.ai.hasil', ['a' => $a])
    @endif

    @php($terakhir = $this->terakhir())
    @if ($terakhir->isNotEmpty())
        <div class="ui-card">
            <h3 class="ui-title">Analisis terakhir</h3>
            <div class="ui-wrap" style="margin:.5rem 0 0; box-shadow:none">
                <table class="ui-table" data-testid="terakhir-ai">
                    <thead><tr><th>Nama</th><th>Waktu</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($terakhir as $t)
                            <tr>
                                <td>{{ $t->subject_name }}</td>
                                <td class="nw">{{ $t->created_at->format('d-m-Y H:i') }}</td>
                                <td><span class="ui-badge">{{ \App\Models\AiAnalysis::STATUS[$t->status] }}</span></td>
                                <td class="c"><button type="button" class="ui-btn ui-btn-sm" wire:click="pilih({{ $t->id }})">Lihat</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>
