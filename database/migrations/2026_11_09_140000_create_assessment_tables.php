<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // KKM khusus mata pelajaran; kosong = ikut KKM sekolah (Profil sekolah).
        Schema::table('subjects', fn (Blueprint $t) => $t->unsignedTinyInteger('kkm')->nullable()->after('name'));

        // Sasaran kunci asing gabungan (school_id, id).
        foreach (['semesters'] as $tabel) {
            Schema::table($tabel, fn (Blueprint $t) => $t->unique(['school_id', 'id']));
        }

        // Komponen penilaian milik sekolah: mis. Ulangan Harian 30, UTS 30, UAS 40.
        Schema::create('assessment_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('name', 60);
            $table->unsignedTinyInteger('weight');       // bobot 1..100; nilai akhir dinormalkan terhadap jumlah bobot
            $table->unsignedTinyInteger('sort_order')->default(1);
            $table->boolean('active')->default(true);    // komponen yang dihentikan tidak dihapus agar nilai lama utuh
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->unique(['school_id', 'id']);
        });

        // Satu nilai = satu siswa, satu mata pelajaran, satu komponen, satu semester.
        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('semester_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('classroom_id');   // kelas saat dinilai
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('component_id');
            $table->decimal('value', 5, 2);               // 0 sampai 100
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete(); // penyimpan terakhir
            $table->timestamps();

            $table->unique(['semester_id', 'student_id', 'subject_id', 'component_id'], 'nilai_unik');
            $table->index(['classroom_id', 'subject_id', 'semester_id']);
            $table->foreign(['school_id', 'semester_id'])->references(['school_id', 'id'])->on('semesters')->restrictOnDelete();
            $table->foreign(['school_id', 'student_id'])->references(['school_id', 'id'])->on('students')->restrictOnDelete();
            $table->foreign(['school_id', 'classroom_id'])->references(['school_id', 'id'])->on('classrooms')->restrictOnDelete();
            $table->foreign(['school_id', 'subject_id'])->references(['school_id', 'id'])->on('subjects')->restrictOnDelete();
            $table->foreign(['school_id', 'component_id'])->references(['school_id', 'id'])->on('assessment_components')->restrictOnDelete();
            // Nilai hanya sah untuk mata pelajaran yang memang diampu di kelas itu (ada penugasan guru).
            $table->foreign(['classroom_id', 'subject_id'])->references(['classroom_id', 'subject_id'])->on('teaching_assignments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scores');
        Schema::dropIfExists('assessment_components');
        Schema::table('semesters', fn (Blueprint $t) => $t->dropUnique(['school_id', 'id']));
        Schema::table('subjects', fn (Blueprint $t) => $t->dropColumn('kkm'));
    }
};
