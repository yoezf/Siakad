<x-filament-panels::page>
    <div class="ui-card" data-testid="formulir-prompt">
        <h3 class="ui-title">{{ $this->editId ? 'Ubah prompt' : 'Tulis prompt baru' }}</h3>
        <p class="ui-sub" style="margin-bottom:1rem">
            Tulis instruksi untuk AI, misalnya apa yang harus dideskripsikan dari foto dan bagaimana format jawabannya. Prompt disimpan, lalu dipilih di menu
            <strong>Analisis dengan Prompt</strong> bersama foto yang akan dianalisis. Prompt yang meminta kesimpulan tentang kecerdasan, kesehatan, suku, agama, atau
            watak seseorang ditolak.
        </p>
        <div class="ui-form">
            <label>Nama prompt
                <input type="text" class="ui-input" wire:model="nama" maxlength="100" aria-label="Nama prompt" placeholder="Contoh: Deskripsi benda di foto">
            </label>
        </div>
        <label class="ui-sub" style="display:block; margin:.25rem 0">Isi prompt
            <textarea class="ui-input" wire:model="isi" rows="8" maxlength="4000" aria-label="Isi prompt" style="margin-top:.3rem" placeholder="Contoh: Sebutkan semua benda yang terlihat di foto dan jelaskan warna serta posisinya."></textarea>
        </label>
        <div class="ui-bar" style="margin:1rem 0 0">
            <button type="button" class="ui-btn ui-btn-primary" wire:click="simpan">{{ $this->editId ? 'Simpan perubahan' : 'Simpan prompt' }}</button>
            @if ($this->editId)<button type="button" class="ui-btn" wire:click="batal">Batal</button>@endif
        </div>
    </div>

    @php($daftar = $this->daftar())
    @if ($daftar->isEmpty())
        <div class="ui-empty" data-testid="prompt-kosong">Belum ada prompt tersimpan.</div>
    @else
        <div class="ui-wrap">
            <table class="ui-table" data-testid="daftar-prompt">
                <thead><tr><th>Nama</th><th>Isi</th><th></th></tr></thead>
                <tbody>
                    @foreach ($daftar as $p)
                        <tr>
                            <td class="top nw"><strong>{{ $p->name }}</strong></td>
                            <td class="top">{{ \Illuminate\Support\Str::limit($p->body, 160) }}</td>
                            <td class="top c nw">
                                <button type="button" class="ui-btn ui-btn-sm" wire:click="ubah({{ $p->id }})">Ubah</button>
                                <button type="button" class="ui-btn ui-btn-sm ui-btn-warn" wire:click="hapus({{ $p->id }})" wire:confirm="Hapus prompt ini?">Hapus</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
