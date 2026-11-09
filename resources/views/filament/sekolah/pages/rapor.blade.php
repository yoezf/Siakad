<x-filament-panels::page>
    {{-- Gaya sebaris: halaman khusus ini tidak memakai tema Tailwind buatan sendiri. --}}
    @php($daftar = $this->daftar())
    @php($d = $this->detail())
    <div style="display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; margin-bottom:1rem">
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
    </div>

    @if ($daftar->isEmpty())
        <p>Tidak ada siswa. Pilih semester dan kelas yang berhak Anda lihat.</p>
    @else
        <div style="overflow-x:auto; margin-bottom:1.5rem">
            <table style="border-collapse:collapse; width:100%; min-width:32rem" data-testid="daftar-rapor">
                <thead>
                    <tr>
                        <th style="border:1px solid #9ca3af; padding:.5rem; text-align:left">Siswa</th>
                        <th style="border:1px solid #9ca3af; padding:.5rem">Status</th>
                        <th style="border:1px solid #9ca3af; padding:.5rem">Versi</th>
                        <th style="border:1px solid #9ca3af; padding:.5rem"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($daftar as $baris)
                        <tr>
                            <td style="border:1px solid #9ca3af; padding:.5rem">{{ $baris['student']->name }}</td>
                            <td style="border:1px solid #9ca3af; padding:.5rem; text-align:center">{{ \App\Models\ReportCard::STATUS[$baris['rapor']?->status ?? 'draf'] }}</td>
                            <td style="border:1px solid #9ca3af; padding:.5rem; text-align:center">{{ ($baris['rapor']?->current_version ?? 0) > 0 ? $baris['rapor']->current_version : '-' }}</td>
                            <td style="border:1px solid #9ca3af; padding:.5rem; text-align:center">
                                <button type="button" wire:click="pilih({{ $baris['student']->id }})" style="padding:.3rem .8rem; border-radius:.4rem; border:1px solid #9ca3af; background:transparent; color:inherit">Buka</button>
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
        <div style="border:1px solid #9ca3af; border-radius:.75rem; padding:1rem" data-testid="detail-rapor">
            <h3 style="font-weight:700; font-size:1.1rem; margin-bottom:.25rem">{{ $d['siswa']->name }} <small style="font-weight:400; opacity:.75">Kelas {{ $d['kelas']?->name }}</small></h3>
            <p style="margin-bottom:.75rem">
                Status: <strong>{{ \App\Models\ReportCard::STATUS[$rapor?->status ?? 'draf'] }}</strong>
                @if ($rapor && $rapor->current_version > 0) &nbsp;|&nbsp; Versi berlaku: <strong>{{ $rapor->current_version }}</strong> @endif
            </p>

            @if ($rapor && ! $rapor->utuh())
                <p style="padding:.5rem .75rem; border-radius:.5rem; background:#fee2e2; color:#7f1d1d; margin-bottom:.75rem" data-testid="integritas-rapor"><strong>Peringatan integritas:</strong> salinan rapor yang disahkan tidak cocok dengan sidiknya atau ada versi yang hilang. Kemungkinan data diubah di luar sistem. Hubungi pengelola platform sebelum rapor ini dipakai.</p>
            @endif

            @if ($terkunci)
                <p style="padding:.5rem .75rem; border-radius:.5rem; background:#e0f2fe; color:#0c4a6e; margin-bottom:.75rem">Rapor ini sudah disahkan. Nilai, kehadiran, dan catatan wali kelas terkunci. Isi di bawah adalah data yang berjalan; rapor resmi adalah salinan versi {{ $rapor->current_version }}.</p>
            @elseif ($rapor?->status === 'revisi')
                <p style="padding:.5rem .75rem; border-radius:.5rem; background:#fef3c7; color:#78350f; margin-bottom:.75rem">Rapor dalam revisi (alasan: {{ $rapor->unlock_reason }}). Perbaiki nilai atau kehadiran, lalu sahkan ulang sebagai versi {{ $rapor->current_version + 1 }}.</p>
            @endif

            @if (count($d['hasil']['masalah']) > 0)
                <div style="padding:.5rem .75rem; border-radius:.5rem; background:#fee2e2; color:#7f1d1d; margin-bottom:.75rem" data-testid="masalah-rapor">
                    <strong>Belum bisa disahkan:</strong>
                    <ul style="margin:.25rem 0 0 1.25rem; list-style:disc">
                        @foreach ($d['hasil']['masalah'] as $m) <li>{{ $m }}</li> @endforeach
                    </ul>
                </div>
            @endif
            @if (count($d['hasil']['peringatan']) > 0)
                <div style="padding:.5rem .75rem; border-radius:.5rem; background:#fef3c7; color:#78350f; margin-bottom:.75rem" data-testid="peringatan-rapor">
                    <strong>Perlu diperhatikan:</strong>
                    <ul style="margin:.25rem 0 0 1.25rem; list-style:disc">
                        @foreach ($d['hasil']['peringatan'] as $m) <li>{{ $m }}</li> @endforeach
                    </ul>
                </div>
            @endif

            @if ($s)
                <div style="overflow-x:auto">
                    <table style="border-collapse:collapse; width:100%; min-width:32rem" data-testid="nilai-rapor">
                        <thead>
                            <tr>
                                <th style="border:1px solid #9ca3af; padding:.4rem; text-align:left">Mata pelajaran</th>
                                <th style="border:1px solid #9ca3af; padding:.4rem">KKM</th>
                                <th style="border:1px solid #9ca3af; padding:.4rem">Nilai akhir</th>
                                <th style="border:1px solid #9ca3af; padding:.4rem">Predikat</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($s['mapel'] as $m)
                                <tr>
                                    <td style="border:1px solid #9ca3af; padding:.4rem">{{ $m['nama'] }}</td>
                                    <td style="border:1px solid #9ca3af; padding:.4rem; text-align:center">{{ $m['kkm'] }}</td>
                                    <td style="border:1px solid #9ca3af; padding:.4rem; text-align:center"><strong>{{ $m['bulat'] ?? '-' }}</strong></td>
                                    <td style="border:1px solid #9ca3af; padding:.4rem; text-align:center">{{ $m['predikat'] ?? '-' }}</td>
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
                <textarea wire:model="catatan" rows="3" maxlength="1000" aria-label="Catatan wali kelas" style="width:100%; padding:.5rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit"></textarea>
                <button type="button" wire:click="simpanCatatan" style="margin-top:.5rem; padding:.4rem 1rem; border-radius:.5rem; border:1px solid #9ca3af; background:transparent; color:inherit">Simpan catatan</button>
            @else
                <p style="opacity:.85">{{ $rapor?->homeroom_note ?: '-' }}</p>
            @endif

            <div style="display:flex; gap:.75rem; flex-wrap:wrap; margin-top:1rem; align-items:center">
                @if ($d['bisaMengesahkan'] && ! $terkunci)
                    <button type="button" wire:click="sahkan" wire:confirm="Sahkan rapor ini? Nilai dan kehadiran siswa akan dikunci." @disabled(count($d['hasil']['masalah']) > 0)
                        style="padding:.5rem 1rem; border-radius:.5rem; border:0; background:#0891b2; color:#fff; font-weight:600; {{ count($d['hasil']['masalah']) > 0 ? 'opacity:.5' : '' }}">
                        {{ $rapor?->status === 'revisi' ? 'Sahkan ulang (versi '.($rapor->current_version + 1).')' : 'Sahkan rapor' }}
                    </button>
                @endif
            </div>

            @if ($terkunci && $d['bisaBukaKunci'])
                <div style="margin-top:1rem; padding:.75rem; border:1px dashed #9ca3af; border-radius:.5rem">
                    <strong>Buka kunci untuk revisi</strong>
                    <p style="margin:.25rem 0 .5rem; opacity:.8"><small>Versi yang sudah disahkan tidak berubah. Hasil revisi menjadi versi baru. Alasan wajib diisi dan tercatat.</small></p>
                    <input type="text" wire:model="alasan" maxlength="255" placeholder="Alasan revisi (minimal 10 karakter)" aria-label="Alasan revisi" style="width:100%; padding:.4rem .6rem; border-radius:.4rem; border:1px solid #9ca3af; background:transparent; color:inherit">
                    <button type="button" wire:click="bukaKunci" wire:confirm="Buka kunci rapor ini?" style="margin-top:.5rem; padding:.4rem 1rem; border-radius:.5rem; border:1px solid #b45309; background:transparent; color:inherit">Buka kunci</button>
                </div>
            @endif

            @if ($d['versi']->isNotEmpty())
                <h4 style="font-weight:600; margin:1rem 0 .25rem">Versi yang sudah disahkan</h4>
                <ul style="list-style:none; padding:0" data-testid="versi-rapor">
                    @foreach ($d['versi'] as $v)
                        <li style="display:flex; gap:.75rem; align-items:center; padding:.3rem 0">
                            <span>Versi {{ $v->version }} &nbsp;
                                <small style="opacity:.75">{{ $v->approved_at->format('d-m-Y H:i') }}{{ $v->version === $rapor->current_version ? ' (berlaku)' : ' (digantikan)' }}{{ $v->revision_reason ? ' | revisi: '.$v->revision_reason : '' }}</small></span>
                            <button type="button" wire:click="unduh({{ $v->id }})" style="padding:.25rem .8rem; border-radius:.4rem; border:1px solid #9ca3af; background:transparent; color:inherit">Unduh PDF</button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</x-filament-panels::page>
