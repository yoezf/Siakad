<x-filament-panels::page>
    @if ($this->bisaMembuat())
        @php($prompt = $this->daftarPrompt())
        <div class="ui-card" data-testid="formulir-ai">
            <h3 class="ui-title">Analisis foto dengan prompt</h3>
            <p class="ui-sub" style="margin-bottom:1rem">Pilih prompt tersimpan, lalu foto yang ingin dianalisis menurut prompt itu. JPG, PNG, atau WEBP, maksimal 10 MB.</p>

            @if ($prompt->isEmpty())
                <div class="ui-alert ui-alert-info" data-testid="belum-ada-prompt">Belum ada prompt tersimpan. Tulis dulu di menu <strong>Prompt Kustom</strong>.</div>
            @else
                <div class="ui-form">
                    <label>Prompt
                        <select class="ui-select" wire:model.live="promptId" aria-label="Prompt" style="width:100%">
                            <option value="">Pilih prompt...</option>
                            @foreach ($prompt as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                        </select>
                    </label>
                    <label>Keterangan foto (opsional)
                        <input type="text" class="ui-input" wire:model="subjek" maxlength="150" aria-label="Keterangan foto">
                    </label>
                </div>
                @php($dipilih = $prompt->firstWhere('id', $this->promptId))
                @if ($dipilih)
                    <details style="margin-bottom:1rem"><summary class="ui-sub" style="cursor:pointer">Lihat isi prompt</summary><pre class="ui-json">{{ $dipilih->body }}</pre></details>
                @endif

                <div class="ui-foto-pilih" data-testid="pilih-foto">
                    <label class="ui-btn">Ambil foto (kamera)
                        <input type="file" hidden wire:model="foto" accept="image/*" capture="environment" aria-label="Ambil foto dengan kamera">
                    </label>
                    <label class="ui-btn">Pilih dari galeri
                        <input type="file" hidden wire:model="foto" accept="image/jpeg,image/png,image/webp" aria-label="Pilih foto dari galeri">
                    </label>
                    <span class="ui-sub" wire:loading.remove wire:target="foto">{{ $foto ? $foto->getClientOriginalName() : 'Belum ada foto dipilih' }}</span>
                    <span class="ui-sub" wire:loading wire:target="foto">Mengunggah foto...</span>
                </div>

                <div class="ui-bar" style="margin:1rem 0 0">
                    <button type="button" class="ui-btn ui-btn-primary" wire:click="kirim" wire:loading.attr="disabled" wire:target="kirim,foto"><span wire:loading.remove wire:target="kirim">Analisis foto</span><span wire:loading wire:target="kirim">Menganalisis foto... mohon tunggu</span></button>
                </div>
            @endif
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
                    <thead><tr><th>Prompt</th><th>Waktu</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($terakhir as $t)
                            <tr>
                                <td>{{ $t->prompt_name ?? '-' }}</td>
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
