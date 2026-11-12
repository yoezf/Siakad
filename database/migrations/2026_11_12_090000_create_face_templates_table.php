<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Data numerik wajah siswa untuk absensi wajah. Foto TIDAK disimpan; isinya terenkripsi dan satu baris per siswa.
        Schema::create('face_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('student_id');
            $table->longText('descriptors'); // JSON terenkripsi: beberapa vektor 128 angka
            $table->unsignedTinyInteger('samples');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique('student_id');
            $table->foreign(['school_id', 'student_id'])->references(['school_id', 'id'])->on('students')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_templates');
    }
};
