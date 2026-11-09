<x-filament-panels::page>
    @php($grid = $this->grid())
    {{-- Gaya sebaris: halaman khusus ini tidak memakai tema Tailwind buatan sendiri. --}}
    <div style="display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; margin-bottom:1rem">
        <select wire:model.live="mode" aria-label="Tampilkan menurut" style="padding:.5rem .75rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit">
            <option value="kelas">Per kelas</option>
            <option value="guru">Per guru</option>
        </select>
        <select wire:model.live="pilihan" aria-label="Pilihan" style="padding:.5rem .75rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit; min-width:14rem">
            @foreach ($this->opsi() as $id => $nama)
                <option value="{{ $id }}">{{ $nama }}</option>
            @endforeach
        </select>
    </div>

    @if ($grid['jam']->isEmpty())
        <p>Belum ada jam pelajaran. Tambahkan di menu <strong>Jam pelajaran</strong>.</p>
    @else
        <div style="overflow-x:auto">
            <table style="border-collapse:collapse; width:100%; min-width:40rem" data-testid="jadwal-mingguan">
                <thead>
                    <tr>
                        <th style="border:1px solid #9ca3af; padding:.5rem; text-align:left">Jam</th>
                        @foreach ($grid['hari'] as $hari)
                            <th style="border:1px solid #9ca3af; padding:.5rem">{{ $hari }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($grid['jam'] as $jam)
                        <tr>
                            <td style="border:1px solid #9ca3af; padding:.5rem; white-space:nowrap">
                                <strong>{{ $jam->name }}</strong><br><small>{{ $jam->rentang() }}</small>
                            </td>
                            @if ($jam->is_break)
                                <td colspan="{{ count($grid['hari']) }}" style="border:1px solid #9ca3af; padding:.5rem; text-align:center; opacity:.7">Istirahat</td>
                            @else
                                @foreach ($grid['hari'] as $no => $hari)
                                    <td style="border:1px solid #9ca3af; padding:.5rem; vertical-align:top">
                                        @foreach ($grid['sel']->get($jam->id.'-'.$no, collect()) as $e)
                                            <strong>{{ $e->subject->name }}</strong><br>
                                            <small>{{ $mode === 'guru' ? 'Kelas '.$e->classroom->name : $e->staff->name }}</small>
                                        @endforeach
                                    </td>
                                @endforeach
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
