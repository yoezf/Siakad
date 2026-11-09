<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kehadiran siswa. schedule_entry_id kosong = kehadiran harian; terisi = kehadiran pada satu pelajaran.
        Schema::create('student_attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('classroom_id');
            $table->unsignedBigInteger('schedule_entry_id')->nullable();
            // Kolom turunan: NULL tidak dianggap kembar oleh indeks unik, jadi kehadiran harian (tanpa pelajaran) perlu
            // nilai 0 agar tetap unik. Dengan ini, satu siswa hanya punya satu catatan per tanggal per pelajaran (atau harian).
            $table->unsignedBigInteger('entry_key')->storedAs('IFNULL(schedule_entry_id, 0)');
            $table->date('date');
            $table->char('status', 1); // H hadir, S sakit, I izin, A alpa, T terlambat
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete(); // yang terakhir menyimpan
            $table->timestamps();

            $table->unique(['student_id', 'date', 'entry_key'], 'kehadiran_siswa_unik');
            $table->index(['classroom_id', 'date']);
            $table->foreign(['school_id', 'student_id'])->references(['school_id', 'id'])->on('students')->restrictOnDelete();
            $table->foreign(['school_id', 'classroom_id'])->references(['school_id', 'id'])->on('classrooms')->restrictOnDelete();
            $table->foreign('schedule_entry_id')->references('id')->on('schedule_entries')->restrictOnDelete();
        });

        // Kehadiran guru dan tenaga kependidikan: satu catatan per orang per tanggal.
        Schema::create('staff_attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('staff_id');
            $table->date('date');
            $table->char('status', 1);
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['staff_id', 'date']);
            $table->index(['school_id', 'date']);
            $table->foreign(['school_id', 'staff_id'])->references(['school_id', 'id'])->on('staff')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_attendances');
        Schema::dropIfExists('student_attendances');
    }
};
