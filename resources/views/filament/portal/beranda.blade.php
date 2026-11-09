<x-filament-panels::page>
    {{-- Gaya sebaris: halaman khusus ini tidak memakai tema Tailwind buatan sendiri. --}}
    @php($anak = $this->anak())
    @php($th = 'border:1px solid #9ca3af; padding:.5rem')
    @if (! $anak)
        <p data-testid="tanpa-anak">Akun Anda belum terhubung ke data siswa. Hubungi admin sekolah.</p>
    @else
        <div style="display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; margin-bottom:1rem">
            @if ($this->daftarAnak()->count() > 1)
                <select wire:model.live="anakId" aria-label="Anak" style="padding:.5rem .75rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit; min-width:10rem">
                    @foreach ($this->daftarAnak() as $a)
                        <option value="{{ $a->id }}">{{ $a->name }}</option>
                    @endforeach
                </select>
            @else
                <strong data-testid="nama-anak">{{ $anak->name }}</strong>
            @endif
            <select wire:model.live="semesterId" aria-label="Semester" style="padding:.5rem .75rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit; min-width:12rem">
                @foreach ($this->opsiSemester() as $id => $nama)
                    <option value="{{ $id }}">{{ $nama }}</option>
                @endforeach
            </select>
        </div>

        <div style="display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:1rem">
            @foreach (\App\Filament\Portal\Pages\Beranda::TAB as $kode => $label)
                <button type="button" wire:click="pilihTab('{{ $kode }}')" style="padding:.4rem 1rem; border-radius:.5rem; border:1px solid #9ca3af; background:{{ $tab === $kode ? '#059669' : 'transparent' }}; color:{{ $tab === $kode ? '#fff' : 'inherit' }}">{{ $label }}</button>
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
                        <p style="padding:.5rem .75rem; border-radius:.5rem; background:#fee2e2; color:#7f1d1d; margin-bottom:.75rem">Rapor ini sedang diperiksa pihak sekolah. Hubungi sekolah.</p>
                    @endunless
                    <div style="overflow-x:auto">
                        <table style="border-collapse:collapse; width:100%; min-width:24rem">
                            <thead><tr><th style="{{ $th }}; text-align:left">Mata pelajaran</th><th style="{{ $th }}">KKM</th><th style="{{ $th }}">Nilai akhir</th><th style="{{ $th }}">Predikat</th></tr></thead>
                            <tbody>
                                @foreach ($s['mapel'] as $m)
                                    <tr>
                                        <td style="{{ $th }}">{{ $m['nama'] }}</td>
                                        <td style="{{ $th }}; text-align:center">{{ $m['kkm'] }}</td>
                                        <td style="{{ $th }}; text-align:center"><strong>{{ $m['bulat'] ?? '-' }}</strong></td>
                                        <td style="{{ $th }}; text-align:center">{{ $m['predikat'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p style="margin:.5rem 0">Rata-rata: <strong>{{ $s['rata'] === null ? '-' : number_format((float) $s['rata'], 2, ',', '') }}</strong></p>
                    <p style="margin:.5rem 0"><strong>Catatan wali kelas:</strong> {{ $s['catatan'] ?? '-' }}</p>
                    <button type="button" wire:click="unduh" style="margin-top:.5rem; padding:.5rem 1rem; border-radius:.5rem; border:0; background:#059669; color:#fff; font-weight:600">Unduh PDF</button>
                </div>
            @endif
        @elseif ($tab === 'nilai')
            @php($n = $this->nilai())
            @if (! $n || $n['hasil']['mapel'] === [])
                <p data-testid="nilai-kosong">Belum ada mata pelajaran atau nilai untuk semester ini.</p>
            @else
                <div style="overflow-x:auto" data-testid="nilai-anak">
                    <table style="border-collapse:collapse; width:100%; min-width:28rem">
                        <thead>
                            <tr>
                                <th style="{{ $th }}; text-align:left">Mata pelajaran</th>
                                @foreach ($n['komponen'] as $k)<th style="{{ $th }}">{{ $k->name }}</th>@endforeach
                                <th style="{{ $th }}">Nilai akhir</th><th style="{{ $th }}">Predikat</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($n['hasil']['mapel'] as $m)
                                <tr>
                                    <td style="{{ $th }}">{{ $m['subject']->name }}</td>
                                    @foreach ($n['komponen'] as $k)<td style="{{ $th }}; text-align:center">{{ isset($m['nilai'][$k->id]) ? rtrim(rtrim(number_format($m['nilai'][$k->id], 2, ',', ''), '0'), ',') : '-' }}</td>@endforeach
                                    <td style="{{ $th }}; text-align:center"><strong>{{ $m['bulat'] ?? '-' }}</strong></td>
                                    <td style="{{ $th }}; text-align:center">{{ $m['predikat'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p style="margin:.5rem 0; opacity:.8"><small>Nilai akhir baru muncul bila semua komponen sudah terisi. Nilai di sini belum final sampai rapor disahkan.</small></p>
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
                        <div style="border:1px solid #9ca3af; border-radius:.75rem; padding:.75rem 1rem; margin-bottom:.75rem">
                            <strong>{{ $a->title }}</strong>
                            <small style="opacity:.75; display:block">{{ $a->published_at?->format('d-m-Y H:i') }}</small>
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
                <div style="overflow-x:auto" data-testid="jadwal-anak">
                    <table style="border-collapse:collapse; width:100%; min-width:36rem">
                        <thead><tr><th style="{{ $th }}; text-align:left">Jam</th>@foreach ($j['hari'] as $hari)<th style="{{ $th }}">{{ $hari }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($j['jam'] as $jam)
                                <tr>
                                    <td style="{{ $th }}; white-space:nowrap"><strong>{{ $jam->name }}</strong><br><small>{{ $jam->rentang() }}</small></td>
                                    @if ($jam->is_break)
                                        <td colspan="{{ count($j['hari']) }}" style="{{ $th }}; text-align:center; opacity:.7">Istirahat</td>
                                    @else
                                        @foreach ($j['hari'] as $no => $hari)
                                            <td style="{{ $th }}; vertical-align:top">
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
