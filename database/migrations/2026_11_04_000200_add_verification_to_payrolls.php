<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->string('slip_number', 65)->nullable()->unique();
            $table->text('verification_token')->nullable();
            $table->char('verification_signature', 64)->nullable();
            $table->timestamp('verified_issued_at')->nullable();
        });
        // Perbaikan hanya pada nilai default R2, jangan menimpa nama kustom pengguna.
        DB::table('app_settings')->where('key','clinic_name')
            ->whereIn('value', ['PT. Mitra Sehat Maju Sentos', 'PT. MITRA SEHAT MAJU SENTOS'])
            ->update(['value'=>'PT. MITRA SEHAT MAJU SENTOSA']);
    }
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['slip_number', 'verification_token', 'verification_signature', 'verified_issued_at']);
        });
        // Identitas perusahaan tidak dikembalikan agar koreksi nama tetap berlaku.
    }
};
