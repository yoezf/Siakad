<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kepala rapor: satu per siswa per semester. Menyimpan status penguncian dan catatan wali yang masih bisa diedit.
        Schema::create('report_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('semester_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('classroom_id');
            $table->string('status', 10)->default('draf'); // draf | disahkan | revisi
            $table->text('homeroom_note')->nullable();     // catatan wali kelas; dikunci saat disahkan
            $table->unsignedSmallInteger('current_version')->default(0); // 0 = belum pernah disahkan
            $table->string('unlock_reason')->nullable();   // alasan buka kunci terakhir
            $table->foreignId('unlocked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamps();

            $table->unique(['semester_id', 'student_id']);
            $table->unique(['school_id', 'id']);
            $table->foreign(['school_id', 'semester_id'])->references(['school_id', 'id'])->on('semesters')->restrictOnDelete();
            $table->foreign(['school_id', 'student_id'])->references(['school_id', 'id'])->on('students')->restrictOnDelete();
            $table->foreign(['school_id', 'classroom_id'])->references(['school_id', 'id'])->on('classrooms')->restrictOnDelete();
        });

        // Salinan tetap tiap pengesahan. Tidak pernah diubah atau dihapus: revisi = versi baru.
        Schema::create('report_card_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('report_card_id');
            $table->unsignedSmallInteger('version');
            $table->json('snapshot');                     // seluruh isi rapor saat disahkan
            $table->char('checksum', 64);                 // sha256 dari bentuk kanonik snapshot, untuk mendeteksi manipulasi
            $table->string('revision_reason')->nullable(); // alasan revisi (versi 2 ke atas)
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at');
            $table->timestamp('created_at')->nullable();

            $table->unique(['report_card_id', 'version']);
            $table->foreign(['school_id', 'report_card_id'])->references(['school_id', 'id'])->on('report_cards')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_card_versions');
        Schema::dropIfExists('report_cards');
    }
};
