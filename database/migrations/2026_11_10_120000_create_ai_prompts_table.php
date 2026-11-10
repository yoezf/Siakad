<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prompt buatan pengguna yang disimpan per sekolah, dipakai untuk menganalisis foto.
        Schema::create('ai_prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('name', 100);
            $table->text('body');
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        // Salinan prompt yang dipakai pada tiap analisis: riwayat tetap benar walau prompt diubah atau dihapus kemudian.
        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->string('prompt_name', 100)->nullable()->after('subject_name');
            $table->text('prompt_text')->nullable()->after('prompt_name');
        });
    }

    public function down(): void
    {
        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->dropColumn(['prompt_name', 'prompt_text']);
        });
        Schema::dropIfExists('ai_prompts');
    }
};
