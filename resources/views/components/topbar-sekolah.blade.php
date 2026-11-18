@php
    $sekolah = \Filament\Facades\Filament::getTenant();
    $url = $sekolah && filament()->hasTenantProfile() && \App\Filament\Sekolah\Pages\ProfilSekolah::canView($sekolah) ? filament()->getTenantProfileUrl() : null;
    $ikon = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="topbar-sekolah-ikon"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z"/></svg>';
@endphp
@if ($sekolah)
    {{-- Nama sekolah di sebelah tombol menu, dipisah garis tipis; menjadi tautan ke profil sekolah bagi yang berhak. --}}
    @if ($url)
        <a href="{{ $url }}" class="topbar-sekolah topbar-sekolah-{{ $varian ?? 'lg' }}" data-testid="nama-sekolah" title="Profil sekolah">{!! $ikon !!}<span>{{ $sekolah->name }}</span></a>
    @else
        <span class="topbar-sekolah topbar-sekolah-{{ $varian ?? 'lg' }}" data-testid="nama-sekolah">{!! $ikon !!}<span>{{ $sekolah->name }}</span></span>
    @endif
@endif
