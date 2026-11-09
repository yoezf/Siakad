<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('name', 20); // mis. 2025/2026
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('number'); // 1 = Ganjil, 2 = Genap
            $table->date('start_date');
            $table->date('end_date');
            // Bernilai 1 untuk semester aktif dan NULL untuk lainnya. Karena NULL tidak dianggap kembar oleh
            // indeks unik, basis data sendiri menjamin paling banyak satu semester aktif per sekolah.
            $table->boolean('aktif')->nullable();
            $table->timestamps();

            $table->unique(['academic_year_id', 'number']);
            $table->unique(['school_id', 'aktif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semesters');
        Schema::dropIfExists('academic_years');
    }
};
