<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pakai pada rute: ->middleware('fitur:ujian'). Fitur yang dimatikan sekolah ditolak di server. */
class EnsureFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $school = Filament::getTenant() ?? $request->user()?->school;
        abort_unless($school?->isActive() && $school->hasFeature($feature), 403, 'Fitur ini tidak aktif untuk sekolah Anda.');

        return $next($request);
    }
}
