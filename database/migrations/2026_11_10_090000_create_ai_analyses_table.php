<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('type', 12); // palmistry | physiognomy
            $table->string('subject_name', 150); // nama orang yang difoto
            // Persetujuan sebelum foto diproses (siapa, hubungan, kapan, versi teksnya).
            $table->string('consent_name', 150);
            $table->string('consent_relation', 16); // diri_sendiri | orang_tua_wali
            $table->timestamp('consented_at');
            $table->string('consent_version', 20);
            $table->string('image_path')->nullable(); // disk privat; NULL setelah foto dihapus
            $table->string('status', 10)->default('menunggu'); // menunggu | diproses | selesai | gagal
            $table->json('result')->nullable(); // JSON terstruktur hasil AI yang sudah disaring
            $table->text('summary')->nullable(); // deskripsi bahasa Indonesia
            $table->string('readability', 8)->nullable(); // baik | cukup | buruk
            $table->string('model', 60)->nullable();
            $table->string('error', 255)->nullable(); // pesan aman, tanpa kunci atau isi respons mentah
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'type', 'created_at']);
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_analyses');
    }
};
