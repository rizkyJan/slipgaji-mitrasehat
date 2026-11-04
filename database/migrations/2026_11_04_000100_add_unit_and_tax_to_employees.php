<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('branch', 120)->nullable();
            $table->string('pph21_ptkp', 5)->default('TK/0');
            $table->string('pph21_mode', 12)->default('auto');
        });
        // Hanya ubah nama default bawaan R1. Jangan menimpa nama khusus yang sudah diganti admin.
        DB::table('app_settings')->where('key', 'clinic_name')->where('value', 'Klinik Pratama Mitra Sehat')->update(['value' => 'PT. Mitra Sehat Maju Sentos']);
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['branch', 'pph21_ptkp', 'pph21_mode']);
        });
    }
};
