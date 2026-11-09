<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sasaran kunci asing gabungan (school_id, id): baris penghubung hanya sah bila kedua sisinya satu sekolah.
        foreach (['classrooms', 'subjects', 'staff'] as $tabel) {
            Schema::table($tabel, fn (Blueprint $t) => $t->unique(['school_id', 'id']));
        }

        Schema::table('classrooms', function (Blueprint $table) {
            $table->unsignedBigInteger('homeroom_staff_id')->nullable()->after('grade'); // wali kelas
            // Satu guru menjadi wali paling banyak satu kelas per tahun ajaran (NULL tidak dianggap kembar).
            $table->unique(['academic_year_id', 'homeroom_staff_id']);
            $table->foreign(['school_id', 'homeroom_staff_id'])->references(['school_id', 'id'])->on('staff')->restrictOnDelete();
        });

        // Riwayat penempatan siswa di kelas. Pindah kelas menutup baris lama dan membuka baris baru; tidak ada yang ditimpa.
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('classroom_id');
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            // 1 untuk penempatan yang berlaku, NULL untuk yang sudah ditutup. Indeks unik di bawah menjamin satu siswa
            // hanya punya satu penempatan berlaku per tahun ajaran, dari jalur mana pun.
            $table->boolean('aktif')->nullable();
            $table->date('joined_at');
            $table->date('left_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id', 'aktif']);
            $table->foreign(['school_id', 'student_id'])->references(['school_id', 'id'])->on('students')->restrictOnDelete();
            $table->foreign(['school_id', 'classroom_id'])->references(['school_id', 'id'])->on('classrooms')->restrictOnDelete();
        });

        // Guru pengampu mata pelajaran pada sebuah kelas: satu guru per mata pelajaran per kelas.
        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('classroom_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('staff_id');
            $table->timestamps();

            $table->unique(['classroom_id', 'subject_id']);
            $table->foreign(['school_id', 'classroom_id'])->references(['school_id', 'id'])->on('classrooms')->restrictOnDelete();
            $table->foreign(['school_id', 'subject_id'])->references(['school_id', 'id'])->on('subjects')->restrictOnDelete();
            $table->foreign(['school_id', 'staff_id'])->references(['school_id', 'id'])->on('staff')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_assignments');
        Schema::dropIfExists('enrollments');
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropForeign(['school_id', 'homeroom_staff_id']);
            $table->dropUnique(['academic_year_id', 'homeroom_staff_id']);
            $table->dropColumn('homeroom_staff_id');
        });
        foreach (['classrooms', 'subjects', 'staff'] as $tabel) {
            Schema::table($tabel, fn (Blueprint $t) => $t->dropUnique(['school_id', 'id']));
        }
    }
};
