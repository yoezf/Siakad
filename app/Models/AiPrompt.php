<?php

namespace App\Models;

use App\Modules\Ai\Skema;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** Prompt tersimpan milik sekolah. Isinya diperiksa saat disimpan: permintaan kesimpulan terlarang tentang orang ditolak (lihat Skema::promptTerlarang). */
#[Fillable(['name', 'body'])]
class AiPrompt extends Model
{
    use BelongsToSchool, LogsActivity;

    protected $table = 'ai_prompts';

    protected static function booted(): void
    {
        static::saving(function (AiPrompt $p) {
            $p->name = trim((string) $p->name);
            $p->body = trim((string) $p->body);
            $err = [];
            if (mb_strlen($p->name) < 3 || mb_strlen($p->name) > 100) {
                $err['name'] = 'Nama prompt harus 3 sampai 100 karakter.';
            }
            if (mb_strlen($p->body) < 10 || mb_strlen($p->body) > 4000) {
                $err['body'] = 'Isi prompt harus 10 sampai 4000 karakter.';
            } elseif (($alasan = Skema::promptTerlarang($p->body)) !== null) {
                $err['body'] = $alasan;
            }
            if ($err) {
                throw ValidationException::withMessages($err);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name'])->logOnlyDirty()->useLogName('prompt_ai'); // isi prompt tidak masuk log
    }
}
