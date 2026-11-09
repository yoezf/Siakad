<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->json('levels')->nullable()->after('city'); // kunci jenjang, mis. ["sd"]
            $table->text('address')->nullable()->after('levels');
            $table->string('phone', 30)->nullable()->after('address');
            $table->string('contact_email')->nullable()->after('phone');
            $table->json('settings')->nullable()->after('features'); // kurikulum, kkm, dst.; bawaan di config/sekolah.php
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['levels', 'address', 'phone', 'contact_email', 'settings']);
        });
    }
};
