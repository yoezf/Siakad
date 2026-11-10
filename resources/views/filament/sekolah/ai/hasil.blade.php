{{-- Kartu hasil satu analisis. Dipakai halaman unggah dan riwayat; $a = AiAnalysis yang sudah lolos pemeriksaan hak akses. --}}
@php($r = $a->result)
@php($sibuk = in_array($a->status, ['menunggu', 'diproses'], true))
@php($kelas = ['terlihat_jelas' => 'ui-badge', 'terlihat_samar' => 'ui-badge ui-badge-warn', 'tidak_terlihat' => 'ui-badge ui-badge-mute'])
<div class="ui-card" data-testid="hasil-ai" @if ($sibuk) wire:poll.2s @endif>
    <h3 class="ui-title">{{ \App\Models\AiAnalysis::TYPE[$a->type] }}: {{ $a->subject_name }}</h3>
    <div class="ui-sub">
        {{ $a->created_at->format('d-m-Y H:i') }} &middot; oleh {{ $a->author?->name ?? '-' }} &middot;
        <span class="ui-badge {{ $a->status === 'gagal' ? 'ui-badge-danger' : '' }}">{{ \App\Models\AiAnalysis::STATUS[$a->status] }}</span>
        @if ($a->readability) &middot; Keterbacaan gambar: <strong>{{ \App\Modules\Ai\Skema::KETERBACAAN[$a->readability] }}</strong> @endif
    </div>

    @if ($sibuk)
        <div class="ui-alert ui-alert-info ui-proses" style="margin-top:.75rem" data-testid="proses-ai"><span class="ui-spin" aria-hidden="true"></span>
            <span>Foto sedang dianalisis, {{ max(0, now()->timestamp - $a->created_at->timestamp) }} detik berjalan (biasanya 5 sampai 15 detik). Halaman ini diperbarui otomatis, tidak perlu dimuat ulang.</span></div>
    @elseif ($a->status === 'gagal')
        <div class="ui-alert ui-alert-danger" style="margin-top:.75rem"><strong>Analisis gagal.</strong> {{ $a->error }}</div>
    @elseif ($r)
        @php($foto = $this->fotoDataUri($a))
        @if ($foto)
            <div class="ui-foto" data-testid="foto-ai">
                <img src="{{ $foto }}" alt="Foto yang dianalisis">
                @if (! empty($r['area']))
                    <span class="ui-foto-kotak" data-testid="area-ai" style="top:{{ $r['area'][0] / 10 }}%; left:{{ $r['area'][1] / 10 }}%; height:{{ ($r['area'][2] - $r['area'][0]) / 10 }}%; width:{{ ($r['area'][3] - $r['area'][1]) / 10 }}%"></span>
                @endif
            </div>
        @endif

        <p class="ui-note">{{ $a->summary }}</p>
        @if (! empty($r['keterbacaan_gambar']['alasan']))
            <p class="ui-sub">Keterbacaan: {{ $r['keterbacaan_gambar']['alasan'] }}</p>
        @endif

        <div class="ui-wrap" style="margin-top:1rem">
            <table class="ui-table" data-testid="bagian-ai">
                <thead><tr><th>Bagian</th><th>Keterbacaan</th><th>Deskripsi visual</th><th>Interpretasi tradisi</th></tr></thead>
                <tbody>
                    {{-- MySQL mengurutkan ulang kunci kolom JSON, jadi urutan tampil mengikuti urutan baku modul. --}}
                    @foreach (array_keys(\App\Modules\Ai\Skema::BAGIAN[$a->type]) as $kunci)
                        @continue(! isset($r['bagian'][$kunci]))
                        @php($b = $r['bagian'][$kunci])
                        <tr>
                            <td class="nw top"><strong>{{ $b['label'] }}</strong></td>
                            <td class="top nw"><span class="{{ $kelas[$b['status']] ?? 'ui-badge' }}">{{ \App\Modules\Ai\Skema::STATUS[$b['status']] ?? $b['status'] }}</span></td>
                            <td class="top">{{ $b['deskripsi_visual'] !== '' ? $b['deskripsi_visual'] : '-' }}</td>
                            <td class="top">{{ $b['interpretasi_tradisi'] !== '' ? $b['interpretasi_tradisi'] : '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="ui-kv">
            <div><b>{{ count($r['bagian_terdeteksi']) }}</b><span>Bagian terdeteksi</span></div>
            <div><b>{{ count($r['bagian_tidak_terdeteksi']) }}</b><span>Bagian tidak terdeteksi</span></div>
        </div>
        @if (count($r['bagian_tidak_terdeteksi']) > 0)
            <p class="ui-sub">Tidak terdeteksi: {{ implode(', ', $r['bagian_tidak_terdeteksi']) }}.</p>
        @endif
        @if (! empty($r['disaring']))
            <div class="ui-alert ui-alert-warn">Sebagian teks disaring karena memuat kesimpulan yang tidak diizinkan.</div>
        @endif

        <h4 class="ui-title" style="font-size:.95rem; margin-top:.75rem">Catatan keterbatasan analisis</h4>
        <ul class="ui-sub" style="list-style:disc; margin:.25rem 0 .75rem 1.25rem">
            @foreach ($r['catatan_keterbatasan'] as $c)<li>{{ $c }}</li>@endforeach
        </ul>

        <details data-testid="json-ai">
            <summary class="ui-sub" style="cursor:pointer">Lihat JSON terstruktur</summary>
            <pre class="ui-json">{{ json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </details>
    @endif

    <div class="ui-bar" style="margin:1rem 0 0">
        @if ($a->image_path)
            <button type="button" class="ui-btn ui-btn-sm" wire:click="hapusFoto({{ $a->id }})" wire:confirm="Hapus foto ini? Hasil analisis tetap tersimpan.">Hapus foto</button>
        @else
            <span class="ui-sub">Foto sudah dihapus.</span>
        @endif
        <button type="button" class="ui-btn ui-btn-sm ui-btn-warn" wire:click="hapusAnalisis({{ $a->id }})" wire:confirm="Hapus foto dan seluruh hasil analisis ini? Tidak bisa dikembalikan.">Hapus foto dan hasil</button>
    </div>
</div>
