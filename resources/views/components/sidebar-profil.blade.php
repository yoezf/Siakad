@php
    $u = auth()->user();
    $peran = $u->roles->pluck('name')->map(fn ($n) => $n === \App\Support\Peran::SUPER_ADMIN ? 'Super Admin' : (\App\Support\Peran::SEKOLAH[$n] ?? $n))->first();
    $inisial = collect(preg_split('/\s+/', trim($u->name)))->filter()->take(2)->map(fn ($k) => mb_strtoupper(mb_substr($k, 0, 1)))->implode('');
@endphp
<div class="sb-profil" data-testid="sidebar-profil">
    <span class="sb-avatar">{{ $inisial }}</span>
    <div class="sb-profil-teks"><strong>{{ $u->name }}</strong><span>{{ $peran }}</span></div>
</div>
