<?php

namespace App\Models;

use App\Modules\Kehadiran\Status;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Kehadiran satu siswa pada satu tanggal: harian (tanpa pelajaran) atau pada satu pelajaran terjadwal.
 * Aturan dijaga di sini agar berlaku dari jalur mana pun: siswa harus terdaftar di kelas itu PADA TANGGAL TERSEBUT
 * (menurut riwayat penempatan), tanggal tidak boleh di masa depan atau di luar tahun ajaran kelas, dan pelajaran harus
 * dijadwalkan pada hari itu. Satu siswa hanya punya satu catatan per tanggal per pelajaran (dijaga indeks unik).
 */
#[Fillable(['student_id', 'classroom_id', 'schedule_entry_id', 'date', 'status', 'note'])]
class StudentAttendance extends Model
{
    use BelongsToSchool, LogsActivity;

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    protected static function booted(): void
    {
        static::saving(function (StudentAttendance $a) {
            if (! Status::sah($a->status)) {
                throw ValidationException::withMessages(['status' => 'Status kehadiran harus H, S, I, A, atau T.']);
            }
            // Kehadiran harian ikut tercetak di rapor: terkunci selama rapor semester yang mencakup tanggal itu disahkan.
            if ($a->schedule_entry_id === null && $a->date !== null && ReportCard::terkunciPadaTanggal((int) $a->student_id, $a->date)) {
                throw ValidationException::withMessages(['date' => 'Rapor siswa ini untuk semester tersebut sudah disahkan, jadi kehadirannya terkunci. Buka kunci rapor untuk merevisi.']);
            }
            if ($a->exists && $a->isDirty(['student_id', 'classroom_id', 'date', 'schedule_entry_id'])) {
                throw new LogicException('Siswa, kelas, tanggal, dan pelajaran pada catatan kehadiran tidak boleh diubah; hanya status dan catatan.');
            }
            $a->note = ($a->note === null || trim((string) $a->note) === '') ? null : mb_substr(trim((string) $a->note), 0, 255);
            if (! $a->isDirty('recorded_by') && auth()->id()) {
                $a->recorded_by = auth()->id(); // layanan mengisinya lebih dulu dengan pencatat sebenarnya
            }

            if ($a->exists) {
                return; // pengecekan berikut hanya untuk catatan baru; yang sudah ada hanya boleh berganti status dan catatan
            }
            $tanggal = $a->date;
            if ($tanggal->gt(today())) {
                throw ValidationException::withMessages(['date' => 'Kehadiran tidak boleh dicatat untuk tanggal yang belum tiba.']);
            }
            // Pencarian terkena penyaring sekolah, jadi data sekolah lain dianggap tidak ada.
            $siswa = Student::find($a->student_id);
            if (! $siswa) {
                throw ValidationException::withMessages(['student_id' => 'Siswa tidak ditemukan.']);
            }
            $kelas = Classroom::with('academicYear')->find($a->classroom_id);
            if (! $kelas) {
                throw ValidationException::withMessages(['classroom_id' => 'Kelas tidak ditemukan.']);
            }
            if ($tanggal->lt($kelas->academicYear->start_date) || $tanggal->gt($kelas->academicYear->end_date)) {
                throw ValidationException::withMessages(['date' => "Tanggal berada di luar tahun ajaran {$kelas->academicYear->name}."]);
            }
            $terdaftar = Enrollment::where('student_id', $a->student_id)->where('classroom_id', $a->classroom_id)
                ->whereDate('joined_at', '<=', $tanggal)->where(fn ($q) => $q->whereNull('left_at')->orWhereDate('left_at', '>=', $tanggal))->exists();
            if (! $terdaftar) {
                throw ValidationException::withMessages(['student_id' => "{$siswa->name} tidak terdaftar di kelas {$kelas->name} pada {$tanggal->format('d-m-Y')}."]);
            }
            if ($a->schedule_entry_id !== null) {
                $entri = ScheduleEntry::find($a->schedule_entry_id);
                if (! $entri || $entri->classroom_id !== $kelas->id) {
                    throw ValidationException::withMessages(['schedule_entry_id' => 'Pelajaran tidak ditemukan pada kelas ini.']);
                }
                if ($entri->day !== $tanggal->dayOfWeekIso) {
                    throw ValidationException::withMessages(['schedule_entry_id' => 'Pelajaran ini dijadwalkan hari '.ScheduleEntry::HARI[$entri->day].', bukan hari '.ScheduleEntry::HARI[$tanggal->dayOfWeekIso].'.']);
                }
            }
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function scheduleEntry(): BelongsTo
    {
        return $this->belongsTo(ScheduleEntry::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['student_id', 'classroom_id', 'schedule_entry_id', 'date', 'status', 'note'])->logOnlyDirty()->useLogName('kehadiran_siswa');
    }
}
