<x-filament-panels::page>
    @php($grid = $this->grid())
    {{-- Gaya sebaris: halaman khusus ini tidak memakai tema Tailwind buatan sendiri. --}}
    <div class="ui-bar" >
        <select class="ui-select" wire:model.live="mode" aria-label="Tampilkan menurut" >
            <option value="kelas">Per kelas</option>
            <option value="guru">Per guru</option>
        </select>
        <select class="ui-select" wire:model.live="pilihan" aria-label="Pilihan" style="min-width:14rem">
            @foreach ($this->opsi() as $id => $nama)
                <option value="{{ $id }}">{{ $nama }}</option>
            @endforeach
        </select>
    </div>

    @if ($grid['jam']->isEmpty())
        <p>Belum ada jam pelajaran. Tambahkan di menu <strong>Jam pelajaran</strong>.</p>
    @else
        <div class="ui-wrap" >
            <table class="ui-table" data-testid="jadwal-mingguan" style="min-width:40rem">
                <thead>
                    <tr>
                        <th >Jam</th>
                        @foreach ($grid['hari'] as $hari)
                            <th >{{ $hari }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($grid['jam'] as $jam)
                        <tr>
                            <td class="nw" >
                                <strong>{{ $jam->name }}</strong><br><small>{{ $jam->rentang() }}</small>
                            </td>
                            @if ($jam->is_break)
                                <td class="c ui-muted" colspan="{{ count($grid['hari']) }}" >Istirahat</td>
                            @else
                                @foreach ($grid['hari'] as $no => $hari)
                                    <td class="top" >
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
