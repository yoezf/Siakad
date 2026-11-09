<x-filament-panels::page>
    @include('filament.sekolah.ai.peringatan')
    @php($daftar = $this->daftar())

    <div class="ui-bar">
        <select class="ui-select" wire:model.live="filterJenis" aria-label="Jenis">
            <option value="">Semua jenis</option>
            @foreach (\App\Models\AiAnalysis::TYPE as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
        </select>
        <select class="ui-select" wire:model.live="filterStatus" aria-label="Status">
            <option value="">Semua status</option>
            @foreach (\App\Models\AiAnalysis::STATUS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
        </select>
    </div>

    @if ($daftar->isEmpty())
        <div class="ui-empty" data-testid="riwayat-kosong">Belum ada analisis.</div>
    @else
        <div class="ui-wrap">
            <table class="ui-table" data-testid="riwayat-ai">
                <thead><tr><th>Waktu</th><th>Jenis</th><th>Nama</th><th>Status</th><th>Keterbacaan</th><th>Foto</th><th></th></tr></thead>
                <tbody>
                    @foreach ($daftar as $a)
                        <tr>
                            <td class="nw">{{ $a->created_at->format('d-m-Y H:i') }}</td>
                            <td>{{ \App\Models\AiAnalysis::TYPE[$a->type] }}</td>
                            <td>{{ $a->subject_name }}</td>
                            <td><span class="ui-badge {{ $a->status === 'gagal' ? 'ui-badge-danger' : '' }}">{{ \App\Models\AiAnalysis::STATUS[$a->status] }}</span></td>
                            <td>{{ $a->readability ? \App\Modules\Ai\Skema::KETERBACAAN[$a->readability] : '-' }}</td>
                            <td>{{ $a->image_path ? 'Tersimpan' : 'Dihapus' }}</td>
                            <td class="c nw"><button type="button" class="ui-btn ui-btn-sm" wire:click="pilih({{ $a->id }})">Lihat</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @php($dipilih = $this->analisis())
    @if ($dipilih)
        @include('filament.sekolah.ai.hasil', ['a' => $dipilih])
    @endif
</x-filament-panels::page>
