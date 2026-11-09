<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('nis', 30)->nullable();   // nomor induk sekolah
            $table->string('nisn', 10)->nullable();  // nomor induk siswa nasional
            $table->char('gender', 1);               // L | P
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('status', 20)->default('aktif'); // aktif | lulus | pindah | keluar; siswa tidak pernah dihapus
            $table->timestamps();

            $table->unique(['school_id', 'nisn']);
            $table->unique(['school_id', 'nis']);
            $table->unique(['school_id', 'id']); // sasaran kunci asing gabungan pada tabel penghubung
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete(); // akun masuk, bila punya
            $table->string('name');
            $table->string('nip', 30)->nullable();
            $table->string('kind', 10)->default('guru'); // guru | tendik
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('status', 10)->default('aktif'); // aktif | nonaktif
            $table->timestamps();

            $table->unique(['school_id', 'nip']);
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->timestamps();

            $table->unique(['school_id', 'code']);
        });

        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('name', 30); // mis. 4A
            $table->unsignedTinyInteger('grade'); // tingkat 1-12
            $table->timestamps();

            $table->unique(['school_id', 'academic_year_id', 'name']);
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete(); // akun portal, bila punya
            $table->string('name');
            $table->string('relationship', 10)->default('wali'); // ayah | ibu | wali
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'id']);
        });

        // Kunci asing gabungan: baris penghubung hanya sah bila orang tua dan siswa berasal dari sekolah yang sama.
        Schema::create('guardian_student', function (Blueprint $table) {
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('guardian_id');
            $table->unsignedBigInteger('student_id');
            $table->timestamps();

            $table->primary(['guardian_id', 'student_id']);
            $table->foreign(['school_id', 'guardian_id'])->references(['school_id', 'id'])->on('guardians')->restrictOnDelete();
            $table->foreign(['school_id', 'student_id'])->references(['school_id', 'id'])->on('students')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_student');
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('classrooms');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('students');
    }
};
