<x-filament-widgets::widget>
    <div class="ui-card" data-testid="papan-pengumuman" style="margin:0">
        <h3 class="ui-title" style="margin-bottom:.75rem">Papan pengumuman</h3>
        @forelse ($this->daftar() as $p)
            <div class="ui-notice">
                <div class="ui-notice-isi">
                    <div class="ui-notice-judul">{{ $p->title }}</div>
                    <div class="ui-sub">{{ \App\Models\Announcement::AUDIENCE[$p->audience] ?? '' }}</div>
                </div>
                <span class="ui-chip">{{ ($p->published_at ?? $p->created_at)?->format('d-m-Y') }}</span>
            </div>
        @empty
            <p class="ui-sub">Belum ada pengumuman terbit.</p>
        @endforelse
    </div>
</x-filament-widgets::widget>
