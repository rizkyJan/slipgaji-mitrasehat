<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_transfer_breakdowns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_payroll_id')->constrained('doctor_payrolls')->cascadeOnDelete();
            $table->date('work_date');
            $table->string('session', 10);
            $table->unsignedInteger('bpjs_count')->default(0);
            $table->unsignedInteger('umum_count')->default(0);
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['doctor_payroll_id', 'work_date', 'session'], 'doctor_transfer_days_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_transfer_breakdowns');
    }
};
