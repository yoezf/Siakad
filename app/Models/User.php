<?php

namespace App\Models;

use App\Support\Peran;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

// school_id sengaja tidak boleh diisi massal: sekolah pengguna hanya ditetapkan oleh kode sistem.
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * ID kelas yang menjadi tanggung jawab guru ini pada tahun ajaran berjalan: kelas yang diwalikan atau yang
     * diampu mata pelajarannya. Pengguna tanpa catatan guru, atau tanpa kelas, mendapat daftar kosong.
     *
     * @return list<int>
     */
    public function idKelasSaya(): array
    {
        if ($this->school_id === null) {
            return [];
        }
        $staf = Staff::withoutGlobalScopes()->where('school_id', $this->school_id)->where('user_id', $this->id)->where('status', 'aktif')->value('id');
        $tahun = AcademicYear::tahunBerjalanId($this->school_id);
        if (! $staf || ! $tahun) {
            return [];
        }

        return Classroom::withoutGlobalScopes()->where('school_id', $this->school_id)->where('academic_year_id', $tahun)
            ->where(fn ($q) => $q->where('homeroom_staff_id', $staf)->orWhereIn('id',
                TeachingAssignment::withoutGlobalScopes()->where('school_id', $this->school_id)->where('staff_id', $staf)->select('classroom_id')))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->hasRole(Peran::SUPER_ADMIN),
            'sekolah' => $this->school_id !== null
                && (bool) $this->school?->isActive()
                && $this->hasAnyRole(Peran::PANEL_SEKOLAH),
            'portal' => $this->school_id !== null
                && (bool) $this->school?->isActive()
                && $this->hasAnyRole(Peran::PORTAL),
            default => false,
        };
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->school ? collect([$this->school]) : collect();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof School
            && $tenant->getKey() === $this->school_id
            && $tenant->isActive();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'email', 'school_id'])->logOnlyDirty()->useLogName('pengguna');
    }
}
