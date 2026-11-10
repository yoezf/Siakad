<?php

namespace App\Filament\Sekolah\Pages;

use App\Filament\Sekolah\Support\KelolaAnalisisAi;
use App\Models\AiPrompt;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Menulis, menyimpan, mengubah, dan menghapus prompt yang dipakai halaman Analisis dengan Prompt. */
class PromptKustom extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'Analisis AI';

    protected static ?int $navigationSort = -1;

    protected static ?string $navigationLabel = 'Prompt Kustom';

    protected static ?string $title = 'Prompt Kustom';

    protected static ?string $slug = 'prompt-kustom';

    protected string $view = 'filament.sekolah.pages.ai-prompt';

    public ?int $editId = null;

    public string $nama = '';

    public string $isi = '';

    public static function canAccess(): bool
    {
        return KelolaAnalisisAi::bisaMengakses();
    }

    /** @return Collection<int, AiPrompt> */
    public function daftar(): Collection
    {
        return AiPrompt::orderBy('name')->get();
    }

    public function simpan(): void
    {
        $p = $this->editId ? AiPrompt::find($this->editId) : new AiPrompt;
        if (! $p || ! Gate::allows($p->exists ? 'update' : 'create', $p->exists ? $p : AiPrompt::class)) {
            Notification::make()->danger()->title('Prompt tidak ditemukan atau Anda tidak berhak mengubahnya.')->send();

            return;
        }
        try {
            $p->forceFill(['name' => $this->nama, 'body' => $this->isi]);
            if (! $p->exists) {
                $p->created_by = auth()->id();
            }
            $p->save();
        } catch (ValidationException $e) {
            Notification::make()->danger()->persistent()->title('Prompt belum bisa disimpan')->body(collect($e->errors())->flatten()->implode(' '))->send();

            return;
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            Notification::make()->danger()->title('Sudah ada prompt dengan nama itu. Pakai nama lain.')->send();

            return;
        }
        $this->batal();
        Notification::make()->success()->title('Prompt tersimpan.')->send();
    }

    public function ubah(int $id): void
    {
        $p = AiPrompt::find($id);
        if ($p && Gate::allows('update', $p)) {
            [$this->editId, $this->nama, $this->isi] = [$p->id, $p->name, $p->body];
        }
    }

    public function batal(): void
    {
        $this->reset('editId', 'nama', 'isi');
    }

    public function hapus(int $id): void
    {
        $p = AiPrompt::find($id);
        if (! $p || ! Gate::allows('delete', $p)) {
            Notification::make()->danger()->title('Prompt tidak ditemukan atau Anda tidak berhak menghapusnya.')->send();

            return;
        }
        $p->delete();
        if ($this->editId === $id) {
            $this->batal();
        }
        Notification::make()->success()->title('Prompt dihapus. Riwayat analisis yang memakainya tetap menyimpan salinan teksnya.')->send();
    }
}
