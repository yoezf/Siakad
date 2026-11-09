<x-filament-panels::page>
    {{-- Gaya sebaris: halaman khusus ini tidak memakai tema Tailwind buatan sendiri. --}}
    @php($komponen = $this->komponen())
    <div style="display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; margin-bottom:1rem">
        <select wire:model.live="mode" aria-label="Tampilan" style="padding:.5rem .75rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit">
            <option value="kelas">Per mata pelajaran</option>
            <option value="siswa">Per siswa</option>
        </select>
        <select wire:model.live="semesterId" aria-label="Semester" style="padding:.5rem .75rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit; min-width:12rem">
            @foreach ($this->opsiSemester() as $id => $nama)
                <option value="{{ $id }}">{{ $nama }}</option>
            @endforeach
        </select>
        <select wire:model.live="kelasId" aria-label="Kelas" style="padding:.5rem .75rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit; min-width:8rem">
            @foreach ($this->opsiKelas() as $id => $nama)
                <option value="{{ $id }}">{{ $nama }}</option>
            @endforeach
        </select>
        @if ($mode === 'kelas')
            <select wire:model.live="mapelId" aria-label="Mata pelajaran" style="padding:.5rem .75rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit; min-width:12rem">
                @foreach ($this->opsiMapel() as $id => $nama)
                    <option value="{{ $id }}">{{ $nama }}</option>
                @endforeach
            </select>
        @else
            <select wire:model.live="siswaId" aria-label="Siswa" style="padding:.5rem .75rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit; min-width:14rem">
                @foreach ($this->opsiSiswa() as $id => $nama)
                    <option value="{{ $id }}">{{ $nama }}</option>
                @endforeach
            </select>
        @endif
    </div>

    @if ($mode === 'kelas')
        @php($baris = $this->barisKelas())
        @if (count($baris) === 0)
            <p>Tidak ada data. Pilih kelas dan mata pelajaran yang berhak Anda lihat.</p>
        @else
            <div style="overflow-x:auto">
                <table style="border-collapse:collapse; width:100%; min-width:40rem" data-testid="rekap-nilai-kelas">
                    <thead>
                        <tr>
                            <th style="border:1px solid #9ca3af; padding:.5rem; text-align:left">Siswa</th>
                            @foreach ($komponen as $k)
                                <th style="border:1px solid #9ca3af; padding:.5rem">{{ $k->name }}<br><small>({{ $k->weight }}%)</small></th>
                            @endforeach
                            <th style="border:1px solid #9ca3af; padding:.5rem">Nilai akhir</th>
                            <th style="border:1px solid #9ca3af; padding:.5rem">Predikat</th>
                            <th style="border:1px solid #9ca3af; padding:.5rem">Tuntas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($baris as $r)
                            <tr>
                                <td style="border:1px solid #9ca3af; padding:.5rem">{{ $r['student']->name }}</td>
                                @foreach ($komponen as $k)
                                    <td style="border:1px solid #9ca3af; padding:.5rem; text-align:center">{{ isset($r['nilai'][$k->id]) ? rtrim(rtrim(number_format($r['nilai'][$k->id], 2, ',', ''), '0'), ',') : '-' }}</td>
                                @endforeach
                                <td style="border:1px solid #9ca3af; padding:.5rem; text-align:center"><strong>{{ $r['bulat'] ?? '-' }}</strong></td>
                                <td style="border:1px solid #9ca3af; padding:.5rem; text-align:center">{{ $r['predikat'] ?? '-' }}</td>
                                <td style="border:1px solid #9ca3af; padding:.5rem; text-align:center">{{ $r['tuntas'] === null ? '-' : ($r['tuntas'] ? 'Ya' : 'Belum') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p style="margin-top:.75rem; opacity:.75"><small>Nilai akhir hanya muncul bila semua komponen sudah terisi. Tuntas dinilai dari nilai bulat terhadap KKM.</small></p>

            @php($riwayat = $this->riwayat())
            @if (count($riwayat) > 0)
                <h3 style="font-weight:600; margin:1.5rem 0 .5rem">Riwayat perubahan nilai (terbaru)</h3>
                <div style="overflow-x:auto">
                    <table style="border-collapse:collapse; width:100%; min-width:36rem" data-testid="riwayat-nilai">
                        <thead>
                            <tr>
                                <th style="border:1px solid #9ca3af; padding:.5rem; text-align:left">Waktu</th>
                                <th style="border:1px solid #9ca3af; padding:.5rem; text-align:left">Siswa</th>
                                <th style="border:1px solid #9ca3af; padding:.5rem; text-align:left">Komponen</th>
                                <th style="border:1px solid #9ca3af; padding:.5rem">Lama</th>
                                <th style="border:1px solid #9ca3af; padding:.5rem">Baru</th>
                                <th style="border:1px solid #9ca3af; padding:.5rem; text-align:left">Oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($riwayat as $h)
                                <tr>
                                    <td style="border:1px solid #9ca3af; padding:.5rem; white-space:nowrap">{{ $h['waktu']->format('d-m-Y H:i') }}</td>
                                    <td style="border:1px solid #9ca3af; padding:.5rem">{{ $h['siswa'] }}</td>
                                    <td style="border:1px solid #9ca3af; padding:.5rem">{{ $h['komponen'] }}</td>
                                    <td style="border:1px solid #9ca3af; padding:.5rem; text-align:center">{{ $h['lama'] ?? '-' }}</td>
                                    <td style="border:1px solid #9ca3af; padding:.5rem; text-align:center">{{ $h['baru'] ?? '-' }}</td>
                                    <td style="border:1px solid #9ca3af; padding:.5rem">{{ $h['oleh'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    @else
        @php($r = $this->ringkasanSiswa())
        @if (count($r['mapel']) === 0)
            <p>Tidak ada data. Pilih kelas dan siswa yang berhak Anda lihat.</p>
        @else
            <div style="overflow-x:auto">
                <table style="border-collapse:collapse; width:100%; min-width:36rem" data-testid="rekap-nilai-siswa">
                    <thead>
                        <tr>
                            <th style="border:1px solid #9ca3af; padding:.5rem; text-align:left">Mata pelajaran</th>
                            <th style="border:1px solid #9ca3af; padding:.5rem">KKM</th>
                            <th style="border:1px solid #9ca3af; padding:.5rem">Nilai akhir</th>
                            <th style="border:1px solid #9ca3af; padding:.5rem">Predikat</th>
                            <th style="border:1px solid #9ca3af; padding:.5rem">Tuntas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($r['mapel'] as $m)
                            <tr>
                                <td style="border:1px solid #9ca3af; padding:.5rem">{{ $m['subject']->name }}</td>
                                <td style="border:1px solid #9ca3af; padding:.5rem; text-align:center">{{ $m['kkm'] }}</td>
                                <td style="border:1px solid #9ca3af; padding:.5rem; text-align:center"><strong>{{ $m['bulat'] ?? '-' }}</strong></td>
                                <td style="border:1px solid #9ca3af; padding:.5rem; text-align:center">{{ $m['predikat'] ?? '-' }}</td>
                                <td style="border:1px solid #9ca3af; padding:.5rem; text-align:center">{{ $m['tuntas'] === null ? '-' : ($m['tuntas'] ? 'Ya' : 'Belum') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p style="margin-top:.75rem">
                @if ($r['penuh'])
                    <strong>Rata-rata:</strong> {{ $r['rata'] === null ? '-' : number_format($r['rata'], 2, ',', '') }}
                    @if (! $r['lengkap']) <small style="opacity:.75">(belum semua mata pelajaran lengkap)</small> @endif
                @else
                    <small style="opacity:.75">Rata-rata hanya tampil bila seluruh mata pelajaran siswa ini dapat Anda lihat.</small>
                @endif
            </p>
        @endif
    @endif
</x-filament-panels::page>
