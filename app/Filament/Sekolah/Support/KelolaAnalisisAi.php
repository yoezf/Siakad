<?php

namespace App\Filament\Sekolah\Support;

use App\Models\AiAnalysis;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/** Bagian bersama halaman analisis AI: memilih, menampilkan foto, dan menghapus. Semua id dari peramban diperiksa ulang dengan kebijakan. */
trait KelolaAnalisisAi
{
    public ?int $analisisId = null;

    /** Analisis terpilih: harus ada di sekolah ini (penyaring sekolah) dan boleh dilihat pengguna. */
    public function analisis(): ?AiAnalysis
    {
        $a = $this->analisisId ? AiAnalysis::find($this->analisisId) : null;

        return $a && Gate::allows('view', $a) ? $a : null;
    }

    public function fotoDataUri(AiAnalysis $a): ?string
    {
        return $a->adaFoto() ? 'data:image/jpeg;base64,'.base64_encode(Storage::disk(AiAnalysis::DISK)->get($a->image_path)) : null;
    }

    public function hapusFoto(int $id): void
    {
        $a = AiAnalysis::find($id);
        if (! $a || ! Gate::allows('delete', $a)) {
            Notification::make()->danger()->title('Analisis tidak ditemukan atau Anda tidak berhak menghapusnya.')->send();

            return;
        }
        $a->hapusFoto();
        Notification::make()->success()->title('Foto dihapus. Hasil analisis tetap tersimpan.')->send();
    }

    public function hapusAnalisis(int $id): void
    {
        $a = AiAnalysis::find($id);
        if (! $a || ! Gate::allows('delete', $a)) {
            Notification::make()->danger()->title('Analisis tidak ditemukan atau Anda tidak berhak menghapusnya.')->send();

            return;
        }
        $a->delete();
        if ($this->analisisId === $id) {
            $this->analisisId = null;
        }
        Notification::make()->success()->title('Foto dan hasil analisis dihapus.')->send();
    }

    public static function bisaMengakses(): bool
    {
        return Gate::allows('viewAny', AiAnalysis::class);
    }
}
