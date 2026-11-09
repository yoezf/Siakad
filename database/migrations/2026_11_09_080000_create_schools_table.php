<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('city')->nullable();
            $table->string('plan')->default('dasar');
            $table->json('features'); // daftar kunci fitur yang aktif
            $table->string('status')->default('aktif'); // aktif | uji_coba | nonaktif
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            // Pengguna sekolah punya school_id; Super Admin (tim developer) tidak.
            $table->foreignId('school_id')->nullable()->after('id')->constrained('schools')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_id');
        });
        Schema::dropIfExists('schools');
    }
};
