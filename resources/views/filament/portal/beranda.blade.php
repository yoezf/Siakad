<x-filament-panels::page>
    {{-- Gaya sebaris: halaman khusus ini tidak memakai tema Tailwind buatan sendiri. --}}
    @php($anak = $this->anak())
    @if (! $anak)
        <p data-testid="tanpa-anak">Akun Anda belum terhubung ke data siswa. Hubungi admin sekolah.</p>
    @else
        <div class="ui-bar" >
            @if ($this->daftarAnak()->count() > 1)
                <select class="ui-select" wire:model.live="anakId" aria-label="Anak" style="min-width:10rem">
                    @foreach ($this->daftarAnak() as $a)
                        <option value="{{ $a->id }}">{{ $a->name }}</option>
                    @endforeach
                </select>
            @else
                <strong data-testid="nama-anak">{{ $anak->name }}</strong>
            @endif
            <select class="ui-select" wire:model.live="semesterId" aria-label="Semester" style="min-width:12rem">
                @foreach ($this->opsiSemester() as $id => $nama)
                    <option value="{{ $id }}">{{ $nama }}</option>
                @endforeach
            </select>
        </div>

        <div class="ui-tabs">
            @foreach (\App\Filament\Portal\Pages\Beranda::TAB as $kode => $label)
                <button type="button" wire:click="pilihTab('{{ $kode }}')" class="ui-tab {{ $tab === $kode ? 'is-active' : '' }}">{{ $label }}</button>
            @endforeach
        </div>

        @if ($tab === 'rapor')
            @php($r = $this->rapor())
            @if (! $r)
                <p data-testid="rapor-kosong">Rapor semester ini belum disahkan.</p>
            @else
                @php($s = $r['snapshot'])
                <div data-testid="rapor-anak">
                    <p style="margin-bottom:.5rem">Versi <strong>{{ $r['versi']->version }}</strong>, disahkan {{ $r['versi']->approved_at->format('d-m-Y') }}.</p>
                    @unless ($r['utuh'])
                        <p class="ui-alert ui-alert-danger" >Rapor ini sedang diperiksa pihak sekolah. Hubungi sekolah.</p>
                    @endunless
                    <div class="ui-wrap" >
                        <table class="ui-table">
                            <thead><tr><th >Mata pelajaran</th><th >KKM</th><th >Nilai akhir</th><th >Predikat</th></tr></thead>
                            <tbody>
                                @foreach ($s['mapel'] as $m)
                                    <tr>
                                        <td >{{ $m['nama'] }}</td>
                                        <td class="c" >{{ $m['kkm'] }}</td>
                                        <td class="c" ><strong>{{ $m['bulat'] ?? '-' }}</strong></td>
                                        <td class="c" >{{ $m['predikat'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p style="margin:.5rem 0">Rata-rata: <strong>{{ $s['rata'] === null ? '-' : number_format((float) $s['rata'], 2, ',', '') }}</strong></p>
                    <p style="margin:.5rem 0"><strong>Catatan wali kelas:</strong> {{ $s['catatan'] ?? '-' }}</p>
                    <button class="ui-btn ui-btn-primary" type="button" wire:click="unduh" style="margin-top:.5rem">Unduh PDF</button>
                </div>
            @endif
        @elseif ($tab === 'nilai')
            @php($n = $this->nilai())
            @if (! $n || $n['hasil']['mapel'] === [])
                <p data-testid="nilai-kosong">Belum ada mata pelajaran atau nilai untuk semester ini.</p>
            @else
                <div class="ui-wrap"  data-testid="nilai-anak">
                    <table class="ui-table" style="min-width:28rem">
                        <thead>
                            <tr>
                                <th >Mata pelajaran</th>
                                @foreach ($n['komponen'] as $k)<th >{{ $k->name }}</th>@endforeach
                                <th >Nilai akhir</th><th >Predikat</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($n['hasil']['mapel'] as $m)
                                <tr>
                                    <td >{{ $m['subject']->name }}</td>
                                    @foreach ($n['komponen'] as $k)<td class="c" >{{ isset($m['nilai'][$k->id]) ? rtrim(rtrim(number_format($m['nilai'][$k->id], 2, ',', ''), '0'), ',') : '-' }}</td>@endforeach
                                    <td class="c" ><strong>{{ $m['bulat'] ?? '-' }}</strong></td>
                                    <td class="c" >{{ $m['predikat'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="ui-muted" style="margin:.5rem 0"><small>Nilai akhir baru muncul bila semua komponen sudah terisi. Nilai di sini belum final sampai rapor disahkan.</small></p>
            @endif
        @elseif ($tab === 'kehadiran')
            @php($h = $this->kehadiran())
            <div data-testid="kehadiran-anak">
                @if (! $h || $h['rekap']['total'] === 0)
                    <p>Belum ada kehadiran tercatat pada semester ini.</p>
                @else
                    <p>Hadir {{ $h['rekap']['H'] }}, terlambat {{ $h['rekap']['T'] }}, sakit {{ $h['rekap']['S'] }}, izin {{ $h['rekap']['I'] }}, alpa {{ $h['rekap']['A'] }}
                        (kehadiran {{ $h['rekap']['persen'] }}% dari {{ $h['rekap']['total'] }} hari tercatat)</p>
                    <ul style="list-style:none; padding:0; margin-top:.75rem">
                        @foreach ($h['harian'] as $d)
                            <li style="padding:.2rem 0">{{ $d->date->format('d-m-Y') }}: {{ \App\Modules\Kehadiran\Status::LABEL[$d->status] ?? $d->status }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @elseif ($tab === 'pengumuman')
            @php($p = $this->pengumuman())
            @if ($p->isEmpty())
                <p data-testid="pengumuman-kosong">Belum ada pengumuman.</p>
            @else
                <div data-testid="pengumuman-daftar">
                    @foreach ($p as $a)
                        <div class="ui-card" style="margin-bottom:.75rem">
                            <strong>{{ $a->title }}</strong>
                            <small class="ui-muted" style="display:block">{{ $a->published_at?->format('d-m-Y H:i') }}</small>
                            <p style="margin-top:.5rem; white-space:pre-line">{{ $a->body }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        @else
            @php($j = $this->jadwal())
            @if (! $j || ! $j['kelas'] || $j['jam']->isEmpty())
                <p data-testid="jadwal-kosong">Jadwal belum tersedia.</p>
            @else
                <p style="margin-bottom:.5rem">Kelas {{ $j['kelas']->name }}</p>
                <div class="ui-wrap"  data-testid="jadwal-anak">
                    <table class="ui-table" style="min-width:36rem">
                        <thead><tr><th >Jam</th>@foreach ($j['hari'] as $hari)<th >{{ $hari }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($j['jam'] as $jam)
                                <tr>
                                    <td class="nw" ><strong>{{ $jam->name }}</strong><br><small>{{ $jam->rentang() }}</small></td>
                                    @if ($jam->is_break)
                                        <td class="c ui-muted" colspan="{{ count($j['hari']) }}" >Istirahat</td>
                                    @else
                                        @foreach ($j['hari'] as $no => $hari)
                                            <td class="top" >
                                                @foreach ($j['sel']->get($jam->id.'-'.$no, collect()) as $e)<strong>{{ $e->subject->name }}</strong><br><small>{{ $e->staff->name }}</small>@endforeach
                                            </td>
                                        @endforeach
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    @endif
</x-filament-panels::page>
