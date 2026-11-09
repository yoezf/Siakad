<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Satu nilai: satu siswa, satu mata pelajaran, satu komponen, satu semester (indeks unik). Aturan dijaga di sini agar
 * berlaku dari jalur mana pun: nilai 0 sampai 100, siswa terdaftar di kelas itu pada tahun ajaran semesternya, mata pelajaran
 * diampu di kelas itu, dan komponennya aktif. Setiap perubahan nilai tercatat di audit (riwayat nilai).
 */
#[Fillable(['semester_id', 'student_id', 'classroom_id', 'subject_id', 'component_id', 'value'])]
class Score extends Model
{
    use BelongsToSchool, LogsActivity;

    // Tanpa cast decimal: cast itu melempar galat umum untuk nilai bukan angka sebelum aturan di bawah sempat menolaknya
    // dengan pesan yang jelas. Kolom basis data (decimal 5,2) tetap menjaga bentuk penyimpanannya.
    protected static function booted(): void
    {
        static::saving(function (Score $n) {
            if (! is_numeric($n->value) || (float) $n->value < 0 || (float) $n->value > 100) {
                throw ValidationException::withMessages(['value' => 'Nilai harus berupa angka 0 sampai 100.']);
            }
            $n->value = round((float) $n->value, 2);
            // Rapor yang sudah disahkan mengunci seluruh nilai siswa itu pada semester itu. Satu-satunya jalan: buka kunci rapor.
            if (ReportCard::terkunci((int) $n->semester_id, (int) $n->student_id)) {
                throw ValidationException::withMessages(['value' => 'Rapor siswa ini sudah disahkan, jadi nilainya terkunci. Buka kunci rapor (dengan alasan) untuk merevisi.']);
            }
            if ($n->exists && $n->isDirty(['semester_id', 'student_id', 'subject_id', 'component_id'])) {
                throw new LogicException('Semester, siswa, mata pelajaran, dan komponen pada sebuah nilai tidak boleh diubah; hanya nilainya.');
            }
            if (! $n->isDirty('recorded_by') && auth()->id()) {
                $n->recorded_by = auth()->id(); // layanan mengisinya lebih dulu dengan penyimpan sebenarnya
            }

            // Pencarian terkena penyaring sekolah, jadi data sekolah lain dianggap tidak ada.
            $komponen = AssessmentComponent::find($n->component_id);
            if (! $komponen) {
                throw ValidationException::withMessages(['component_id' => 'Komponen penilaian tidak ditemukan.']);
            }
            if (! $komponen->active) {
                throw ValidationException::withMessages(['component_id' => "Komponen {$komponen->name} sudah dihentikan."]);
            }
            if ($n->exists && ! $n->isDirty(['classroom_id', 'value'])) {
                return;
            }
            $semester = Semester::find($n->semester_id);
            if (! $semester) {
                throw ValidationException::withMessages(['semester_id' => 'Semester tidak ditemukan.']);
            }
            $siswa = Student::find($n->student_id);
            if (! $siswa) {
                throw ValidationException::withMessages(['student_id' => 'Siswa tidak ditemukan.']);
            }
            $kelas = Classroom::find($n->classroom_id);
            if (! $kelas) {
                throw ValidationException::withMessages(['classroom_id' => 'Kelas tidak ditemukan.']);
            }
            if (! Subject::whereKey($n->subject_id)->exists()) {
                throw ValidationException::withMessages(['subject_id' => 'Mata pelajaran tidak ditemukan.']);
            }
            $terdaftar = Enrollment::where('student_id', $n->student_id)->where('classroom_id', $n->classroom_id)
                ->where('academic_year_id', $semester->academic_year_id)->exists();
            if (! $terdaftar) {
                throw ValidationException::withMessages(['student_id' => "{$siswa->name} tidak terdaftar di kelas {$kelas->name} pada tahun ajaran semester ini."]);
            }
            if (! TeachingAssignment::where('classroom_id', $n->classroom_id)->where('subject_id', $n->subject_id)->exists()) {
                throw ValidationException::withMessages(['subject_id' => "Mata pelajaran ini belum punya guru pengampu di kelas {$kelas->name}."]);
            }
        });
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(AssessmentComponent::class, 'component_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Riwayat nilai: nilai lama dan baru, siapa yang mengubah (causer), dan kapan.
        return LogOptions::defaults()->logOnly(['semester_id', 'student_id', 'classroom_id', 'subject_id', 'component_id', 'value'])->logOnlyDirty()->useLogName('nilai');
    }
}
