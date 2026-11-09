<x-filament-panels::page>
    {{-- Gaya sebaris: halaman khusus ini tidak memakai tema Tailwind buatan sendiri. --}}
    @php($daftar = $this->daftar())
    @php($d = $this->detail())
    <div class="ui-bar" >
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
    </div>

    @if ($daftar->isEmpty())
        <p>Tidak ada siswa. Pilih semester dan kelas yang berhak Anda lihat.</p>
    @else
        <div style="overflow-x:auto; margin-bottom:1.5rem">
            <table class="ui-table" data-testid="daftar-rapor" style="min-width:32rem">
                <thead>
                    <tr>
                        <th >Siswa</th>
                        <th >Status</th>
                        <th >Versi</th>
                        <th ></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($daftar as $baris)
                        <tr>
                            <td >{{ $baris['student']->name }}</td>
                            <td class="c" >{{ \App\Models\ReportCard::STATUS[$baris['rapor']?->status ?? 'draf'] }}</td>
                            <td class="c" >{{ ($baris['rapor']?->current_version ?? 0) > 0 ? $baris['rapor']->current_version : '-' }}</td>
                            <td class="c" >
                                <button class="ui-btn ui-btn-sm" type="button" wire:click="pilih({{ $baris['student']->id }})" >Buka</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($d)
        @php($s = $d['hasil']['snapshot'])
        @php($rapor = $d['rapor'])
        @php($terkunci = $rapor?->status === 'disahkan')
        <div class="ui-card"  data-testid="detail-rapor">
            <h3 style="font-weight:700; font-size:1.1rem; margin-bottom:.25rem">{{ $d['siswa']->name }} <small class="ui-muted" style="font-weight:400">Kelas {{ $d['kelas']?->name }}</small></h3>
            <p style="margin-bottom:.75rem">
                Status: <strong>{{ \App\Models\ReportCard::STATUS[$rapor?->status ?? 'draf'] }}</strong>
                @if ($rapor && $rapor->current_version > 0) &nbsp;|&nbsp; Versi berlaku: <strong>{{ $rapor->current_version }}</strong> @endif
            </p>

            @if ($rapor && ! $rapor->utuh())
                <p class="ui-alert ui-alert-danger"  data-testid="integritas-rapor"><strong>Peringatan integritas:</strong> salinan rapor yang disahkan tidak cocok dengan sidiknya atau ada versi yang hilang. Kemungkinan data diubah di luar sistem. Hubungi pengelola platform sebelum rapor ini dipakai.</p>
            @endif

            @if ($terkunci)
                <p class="ui-alert ui-alert-info" >Rapor ini sudah disahkan. Nilai, kehadiran, dan catatan wali kelas terkunci. Isi di bawah adalah data yang berjalan; rapor resmi adalah salinan versi {{ $rapor->current_version }}.</p>
            @elseif ($rapor?->status === 'revisi')
                <p class="ui-alert ui-alert-warn" >Rapor dalam revisi (alasan: {{ $rapor->unlock_reason }}). Perbaiki nilai atau kehadiran, lalu sahkan ulang sebagai versi {{ $rapor->current_version + 1 }}.</p>
            @endif

            @if (count($d['hasil']['masalah']) > 0)
                <div class="ui-alert ui-alert-danger"  data-testid="masalah-rapor">
                    <strong>Belum bisa disahkan:</strong>
                    <ul style="margin:.25rem 0 0 1.25rem; list-style:disc">
                        @foreach ($d['hasil']['masalah'] as $m) <li>{{ $m }}</li> @endforeach
                    </ul>
                </div>
            @endif
            @if (count($d['hasil']['peringatan']) > 0)
                <div class="ui-alert ui-alert-warn"  data-testid="peringatan-rapor">
                    <strong>Perlu diperhatikan:</strong>
                    <ul style="margin:.25rem 0 0 1.25rem; list-style:disc">
                        @foreach ($d['hasil']['peringatan'] as $m) <li>{{ $m }}</li> @endforeach
                    </ul>
                </div>
            @endif

            @if ($s)
                <div class="ui-wrap" >
                    <table class="ui-table" data-testid="nilai-rapor" style="min-width:32rem">
                        <thead>
                            <tr>
                                <th >Mata pelajaran</th>
                                <th >KKM</th>
                                <th >Nilai akhir</th>
                                <th >Predikat</th>
                            </tr>
                        </thead>
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
                <p style="margin:.5rem 0">Rata-rata: <strong>{{ $s['rata'] === null ? '-' : number_format((float) $s['rata'], 2, ',', '') }}</strong>
                    &nbsp;|&nbsp; Kehadiran: hadir {{ $s['kehadiran']['H'] }}, terlambat {{ $s['kehadiran']['T'] }}, sakit {{ $s['kehadiran']['S'] }}, izin {{ $s['kehadiran']['I'] }}, alpa {{ $s['kehadiran']['A'] }}
                    ({{ $s['kehadiran']['persen'] === null ? '-' : $s['kehadiran']['persen'].'%' }})</p>
            @endif

            <h4 style="font-weight:600; margin:1rem 0 .25rem">Catatan wali kelas</h4>
            @if ($d['bisaMenyusun'] && ! $terkunci)
                <textarea class="ui-input" wire:model="catatan" rows="3" maxlength="1000" aria-label="Catatan wali kelas" ></textarea>
                <button class="ui-btn" type="button" wire:click="simpanCatatan" style="margin-top:.5rem">Simpan catatan</button>
            @else
                <p class="ui-muted" >{{ $rapor?->homeroom_note ?: '-' }}</p>
            @endif

            <div class="ui-bar" style="margin-top:1rem">
                @if ($d['bisaMengesahkan'] && ! $terkunci)
                    <button class="ui-btn ui-btn-primary" type="button" wire:click="sahkan" wire:confirm="Sahkan rapor ini? Nilai dan kehadiran siswa akan dikunci." @disabled(count($d['hasil']['masalah']) > 0)>
                        {{ $rapor?->status === 'revisi' ? 'Sahkan ulang (versi '.($rapor->current_version + 1).')' : 'Sahkan rapor' }}
                    </button>
                @endif
            </div>

            @if ($terkunci && $d['bisaBukaKunci'])
                <div class="ui-card" style="margin-top:1rem">
                    <strong>Buka kunci untuk revisi</strong>
                    <p class="ui-muted" style="margin:.25rem 0 .5rem"><small>Versi yang sudah disahkan tidak berubah. Hasil revisi menjadi versi baru. Alasan wajib diisi dan tercatat.</small></p>
                    <input class="ui-input" type="text" wire:model="alasan" maxlength="255" placeholder="Alasan revisi (minimal 10 karakter)" aria-label="Alasan revisi" >
                    <button class="ui-btn ui-btn-warn" type="button" wire:click="bukaKunci" wire:confirm="Buka kunci rapor ini?" style="margin-top:.5rem">Buka kunci</button>
                </div>
            @endif

            @if ($d['versi']->isNotEmpty())
                <h4 style="font-weight:600; margin:1rem 0 .25rem">Versi yang sudah disahkan</h4>
                <ul style="list-style:none; padding:0" data-testid="versi-rapor">
                    @foreach ($d['versi'] as $v)
                        <li style="display:flex; gap:.75rem; align-items:center; padding:.3rem 0">
                            <span>Versi {{ $v->version }} &nbsp;
                                <small class="ui-muted" >{{ $v->approved_at->format('d-m-Y H:i') }}{{ $v->version === $rapor->current_version ? ' (berlaku)' : ' (digantikan)' }}{{ $v->revision_reason ? ' | revisi: '.$v->revision_reason : '' }}</small></span>
                            <button class="ui-btn ui-btn-sm" type="button" wire:click="unduh({{ $v->id }})" >Unduh PDF</button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</x-filament-panels::page>
