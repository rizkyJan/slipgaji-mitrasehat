<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('doctor_fee_days', function (Blueprint $t) {
            $t->id();
            $t->foreignId('doctor_payroll_id')->constrained('doctor_payrolls')->restrictOnDelete();
            $t->date('work_date');
            $t->unsignedBigInteger('fee_amount')->nullable();
            $t->boolean('confirmed')->default(false);
            $t->boolean('payment_checked')->default(false);
            $t->string('source', 24)->default('manual');
            $t->string('note', 500)->nullable();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['doctor_payroll_id', 'work_date'], 'doctor_fee_day_unique');
        });
        Schema::create('doctor_fee_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('doctor_fee_day_id')->constrained('doctor_fee_days')->cascadeOnDelete();
            $t->date('payment_date');
            $t->string('method', 12);
            $t->uuid('request_key')->unique();
            $t->unsignedBigInteger('amount');
            $t->string('note', 500)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('voided_at')->nullable();
            $t->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('void_reason', 500)->nullable();
            $t->timestamps();
            $t->index(['doctor_fee_day_id', 'voided_at'], 'doctor_fee_payments_active');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('doctor_fee_payments');
        Schema::dropIfExists('doctor_fee_days');
    }
};
