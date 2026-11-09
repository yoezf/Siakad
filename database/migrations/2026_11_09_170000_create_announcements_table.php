<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('classroom_id')->nullable(); // hanya untuk sasaran "kelas"
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('title', 150);
            $table->text('body');
            $table->string('audience', 12)->default('semua'); // semua | orang_tua | siswa | kelas
            $table->string('status', 8)->default('draf'); // draf | terbit | arsip
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            // Kelas sasaran harus milik sekolah yang sama.
            $table->foreign(['school_id', 'classroom_id'])->references(['school_id', 'id'])->on('classrooms')->restrictOnDelete();
            $table->index(['school_id', 'status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
