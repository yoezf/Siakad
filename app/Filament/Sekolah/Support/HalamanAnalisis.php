<?php

namespace App\Filament\Sekolah\Support;

use App\Models\AiAnalysis;
use App\Modules\Ai\BuatAnalisis;
use App\Modules\Ai\Skema;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\WithFileUploads;

/** Dasar halaman unggah dan hasil untuk satu jenis analisis. Subkelas hanya menentukan jenis, judul, dan alamat. */
abstract class HalamanAnalisis extends Page
{
    use KelolaAnalisisAi, WithFileUploads;

    protected static string|\UnitEnum|null $navigationGroup = 'Analisis AI';

    protected string $view = 'filament.sekolah.pages.ai-analisis';

    protected static string $jenis;

    public $foto = null;

    public string $subjek = '';

    public string $pemberi = '';

    public string $hubungan = 'diri_sendiri';

    public bool $setuju = false;

    public bool $dewasa = false;

    public static function canAccess(): bool
    {
        return static::bisaMengakses();
    }

    public function jenis(): string
    {
        return static::$jenis;
    }

    public function teksPersetujuan(): string
    {
        return Skema::TEKS_PERSETUJUAN;
    }

    public function bisaMembuat(): bool
    {
        return Gate::allows('create', AiAnalysis::class);
    }

    public function kirim(): void
    {
        if (! $this->foto) {
            Notification::make()->danger()->title('Pilih foto terlebih dahulu.')->send();

            return;
        }
        try {
            $a = BuatAnalisis::handle(auth()->user(), static::$jenis, $this->foto, ['subject_name' => $this->subjek, 'consent_name' => $this->pemberi,
                'consent_relation' => $this->hubungan, 'setuju' => $this->setuju, 'dewasa' => $this->dewasa]);
        } catch (ValidationException $e) {
            Notification::make()->danger()->persistent()->title('Tidak bisa diproses')->body(collect($e->errors())->flatten()->implode(' '))->send();

            return;
        } catch (AuthorizationException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }
        $this->analisisId = $a->id;
        $this->reset('foto', 'setuju', 'dewasa'); // persetujuan harus diberikan lagi untuk foto berikutnya
        Notification::make()->success()->title('Foto diterima dan sedang dianalisis.')->send();
    }

    /** @return Collection<int, AiAnalysis> */
    public function terakhir(): Collection
    {
        return AiAnalysis::where('type', static::$jenis)->latest('id')->limit(5)->get();
    }

    public function pilih(int $id): void
    {
        $this->analisisId = $id;
    }
}
