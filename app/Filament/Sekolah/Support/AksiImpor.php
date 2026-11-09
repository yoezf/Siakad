<?php

namespace App\Filament\Sekolah\Support;

use App\Modules\Impor\Importer;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/** Dua aksi header yang sama untuk semua halaman yang mendukung impor Excel: unduh templat dan impor. */
class AksiImpor
{
    /** @return list<Action> */
    public static function buat(Importer $importer, string $model, string $namaBerkas): array
    {
        $boleh = fn () => auth()->user()?->can('create', $model) ?? false;

        return [
            Action::make('templat')->label('Unduh templat Excel')->color('gray')->authorize($boleh)
                ->action(fn () => response()->streamDownload(fn () => print ($importer->templat()), "templat-{$namaBerkas}.xlsx")),

            Action::make('impor')->label('Impor dari Excel')->color('gray')->authorize($boleh)
                ->modalHeading('Impor dari Excel')
                ->modalDescription('Gunakan templat. Jika ada satu baris bermasalah, tidak ada data yang diimpor dan semua kesalahan ditampilkan. Impor hanya menambah data baru.')
                ->modalSubmitActionLabel('Impor')
                ->schema([
                    FileUpload::make('berkas')->label('Berkas Excel (.xlsx, maksimal 2 MB)')->required()
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->maxSize(2048)->disk('local')->visibility('private')->directory('impor-sementara'),
                ])
                ->action(function (array $data) use ($importer) {
                    $disk = Storage::disk('local');
                    try {
                        $hasil = $importer->jalankan($disk->path($data['berkas']));
                    } finally {
                        $disk->delete($data['berkas']); // berkas sementara tidak disimpan
                    }

                    if ($hasil->berhasil()) {
                        Notification::make()->success()->title("{$hasil->dibuat} baris berhasil diimpor.")->send();

                        return;
                    }
                    $baris = array_map(fn ($g) => ($g['baris'] ? "Baris {$g['baris']}: " : '').ucfirst($g['pesan']), array_slice($hasil->galat, 0, 15));
                    $sisa = count($hasil->galat) - count($baris);
                    if ($sisa > 0) {
                        $baris[] = "... dan {$sisa} kesalahan lain.";
                    }
                    Notification::make()->danger()->persistent()
                        ->title('Impor dibatalkan, tidak ada data yang tersimpan ('.count($hasil->galat).' kesalahan)')
                        ->body(new HtmlString(implode('<br>', array_map('e', $baris))))->send();
                }),
        ];
    }
}
