<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sasaran kunci asing: sebuah pelajaran hanya sah bila (kelas, mata pelajaran, guru) adalah penugasan yang ada.
        Schema::table('teaching_assignments', fn (Blueprint $t) => $t->unique(['classroom_id', 'subject_id', 'staff_id'], 'ta_kelas_mapel_guru'));

        Schema::create('time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('number');   // urutan: jam ke-1, ke-2, dst.
            $table->string('name', 40);              // mis. "Jam ke-1" atau "Istirahat"
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_break')->default(false); // istirahat tidak bisa diisi pelajaran
            $table->timestamps();

            $table->unique(['school_id', 'number']);
            $table->unique(['school_id', 'id']);
        });

        Schema::create('schedule_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('classroom_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('staff_id');      // diisi dari penugasan guru, bukan dari isian pengguna
            $table->unsignedBigInteger('time_slot_id');
            $table->unsignedTinyInteger('day');          // 1 = Senin ... 7 = Minggu
            $table->timestamps();

            // Pencegahan bentrok oleh basis data sendiri, dari jalur mana pun:
            $table->unique(['classroom_id', 'day', 'time_slot_id'], 'jadwal_kelas_unik');   // satu kelas, satu pelajaran per jam
            $table->unique(['staff_id', 'day', 'time_slot_id'], 'jadwal_guru_unik');        // satu guru, satu kelas per jam

            $table->foreign(['school_id', 'classroom_id'])->references(['school_id', 'id'])->on('classrooms')->restrictOnDelete();
            $table->foreign(['school_id', 'time_slot_id'])->references(['school_id', 'id'])->on('time_slots')->restrictOnDelete();
            // Jadwal mengikuti penugasan: bila gurunya diganti pada penugasan, jadwalnya ikut berganti (atau ditolak bila bentrok).
            $table->foreign(['classroom_id', 'subject_id', 'staff_id'])->references(['classroom_id', 'subject_id', 'staff_id'])
                ->on('teaching_assignments')->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_entries');
        Schema::dropIfExists('time_slots');
        Schema::table('teaching_assignments', fn (Blueprint $t) => $t->dropUnique('ta_kelas_mapel_guru'));
    }
};
