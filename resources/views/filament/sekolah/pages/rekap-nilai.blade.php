<x-filament-panels::page>
    {{-- Gaya sebaris: halaman khusus ini tidak memakai tema Tailwind buatan sendiri. --}}
    @php($komponen = $this->komponen())
    <div class="ui-bar" >
        <select class="ui-select" wire:model.live="mode" aria-label="Tampilan" >
            <option value="kelas">Per mata pelajaran</option>
            <option value="siswa">Per siswa</option>
        </select>
        <select class="ui-select" wire:model.live="semesterId" aria-label="Semester" style="min-width:12rem">
            @foreach ($this->opsiSemester() as $id => $nama)
                <option value="{{ $id }}">{{ $nama }}</option>
            @endforeach
        </select>
        <select class="ui-select" wire:model.live="kelasId" aria-label="Kelas" style="min-width:8rem">
            @foreach ($this->opsiKelas() as $id => $nama)
                <option value="{{ $id }}">{{ $nama }}</option>
            @endforeach
        </select>
        @if ($mode === 'kelas')
            <select class="ui-select" wire:model.live="mapelId" aria-label="Mata pelajaran" style="min-width:12rem">
                @foreach ($this->opsiMapel() as $id => $nama)
                    <option value="{{ $id }}">{{ $nama }}</option>
                @endforeach
            </select>
        @else
            <select class="ui-select" wire:model.live="siswaId" aria-label="Siswa" style="min-width:14rem">
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
            <div class="ui-wrap" >
                <table class="ui-table" data-testid="rekap-nilai-kelas" style="min-width:40rem">
                    <thead>
                        <tr>
                            <th >Siswa</th>
                            @foreach ($komponen as $k)
                                <th >{{ $k->name }}<br><small>({{ $k->weight }}%)</small></th>
                            @endforeach
                            <th >Nilai akhir</th>
                            <th >Predikat</th>
                            <th >Tuntas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($baris as $r)
                            <tr>
                                <td >{{ $r['student']->name }}</td>
                                @foreach ($komponen as $k)
                                    <td class="c" >{{ isset($r['nilai'][$k->id]) ? rtrim(rtrim(number_format($r['nilai'][$k->id], 2, ',', ''), '0'), ',') : '-' }}</td>
                                @endforeach
                                <td class="c" ><strong>{{ $r['bulat'] ?? '-' }}</strong></td>
                                <td class="c" >{{ $r['predikat'] ?? '-' }}</td>
                                <td class="c" >{{ $r['tuntas'] === null ? '-' : ($r['tuntas'] ? 'Ya' : 'Belum') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="ui-muted" style="margin-top:.75rem"><small>Nilai akhir hanya muncul bila semua komponen sudah terisi. Tuntas dinilai dari nilai bulat terhadap KKM.</small></p>

            @php($riwayat = $this->riwayat())
            @if (count($riwayat) > 0)
                <h3 style="font-weight:600; margin:1.5rem 0 .5rem">Riwayat perubahan nilai (terbaru)</h3>
                <div class="ui-wrap" >
                    <table class="ui-table" data-testid="riwayat-nilai" style="min-width:36rem">
                        <thead>
                            <tr>
                                <th >Waktu</th>
                                <th >Siswa</th>
                                <th >Komponen</th>
                                <th >Lama</th>
                                <th >Baru</th>
                                <th >Oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($riwayat as $h)
                                <tr>
                                    <td class="nw" >{{ $h['waktu']->format('d-m-Y H:i') }}</td>
                                    <td >{{ $h['siswa'] }}</td>
                                    <td >{{ $h['komponen'] }}</td>
                                    <td class="c" >{{ $h['lama'] ?? '-' }}</td>
                                    <td class="c" >{{ $h['baru'] ?? '-' }}</td>
                                    <td >{{ $h['oleh'] }}</td>
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
            <div class="ui-wrap" >
                <table class="ui-table" data-testid="rekap-nilai-siswa" style="min-width:36rem">
                    <thead>
                        <tr>
                            <th >Mata pelajaran</th>
                            <th >KKM</th>
                            <th >Nilai akhir</th>
                            <th >Predikat</th>
                            <th >Tuntas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($r['mapel'] as $m)
                            <tr>
                                <td >{{ $m['subject']->name }}</td>
                                <td class="c" >{{ $m['kkm'] }}</td>
                                <td class="c" ><strong>{{ $m['bulat'] ?? '-' }}</strong></td>
                                <td class="c" >{{ $m['predikat'] ?? '-' }}</td>
                                <td class="c" >{{ $m['tuntas'] === null ? '-' : ($m['tuntas'] ? 'Ya' : 'Belum') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p style="margin-top:.75rem">
                @if ($r['penuh'])
                    <strong>Rata-rata:</strong> {{ $r['rata'] === null ? '-' : number_format($r['rata'], 2, ',', '') }}
                    @if (! $r['lengkap']) <small class="ui-muted" >(belum semua mata pelajaran lengkap)</small> @endif
                @else
                    <small class="ui-muted" >Rata-rata hanya tampil bila seluruh mata pelajaran siswa ini dapat Anda lihat.</small>
                @endif
            </p>
        @endif
    @endif
</x-filament-panels::page>
