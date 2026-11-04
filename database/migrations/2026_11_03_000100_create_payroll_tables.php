<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('admin');
            $table->boolean('active')->default(true);
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code', 30)->unique();
            $table->string('name', 150);
            $table->string('position', 120)->nullable();
            $table->string('email')->nullable();
            $table->string('employment_status', 30)->default('tetap');
            $table->date('joined_at')->nullable();
            $table->string('payment_method', 30)->default('transfer');
            $table->string('bank_name', 70)->nullable();
            $table->string('bank_account', 70)->nullable();
            $table->unsignedBigInteger('base_salary')->default(0);
            $table->unsignedBigInteger('fixed_allowance')->default(0);
            $table->unsignedBigInteger('meal_allowance')->default(0);
            $table->boolean('meal_is_fixed')->default(false);
            $table->unsignedBigInteger('reported_tk_wage')->nullable();
            $table->unsignedBigInteger('reported_health_wage')->nullable();
            $table->boolean('bpjs_health')->default(true);
            $table->boolean('bpjs_jht')->default(true);
            $table->boolean('bpjs_jkk')->default(true);
            $table->boolean('bpjs_jkm')->default(true);
            $table->boolean('bpjs_jp')->default(false);
            $table->unsignedTinyInteger('family_extra')->default(0);
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('minimum_wages', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('region', 120)->default('Kabupaten Sukoharjo');
            $table->unsignedBigInteger('amount');
            $table->string('reference', 250)->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_rules', function (Blueprint $table) {
            $table->id();
            $table->date('effective_from')->unique();
            $table->decimal('health_employee_pct', 8, 4)->default(1);
            $table->decimal('health_employer_pct', 8, 4)->default(4);
            $table->unsignedBigInteger('health_cap')->default(12000000);
            $table->decimal('jht_employee_pct', 8, 4)->default(2);
            $table->decimal('jht_employer_pct', 8, 4)->default(3.7);
            $table->decimal('jkk_employer_pct', 8, 4)->default(0.54);
            $table->decimal('jkm_employer_pct', 8, 4)->default(0.3);
            $table->decimal('jp_employee_pct', 8, 4)->default(1);
            $table->decimal('jp_employer_pct', 8, 4)->default(2);
            $table->unsignedBigInteger('jp_cap')->nullable();
            $table->boolean('tk_use_umk_floor')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->char('period', 7);
            $table->string('status', 20)->default('draft');
            $table->json('input_snapshot');
            $table->json('calculation_snapshot');
            $table->unsignedBigInteger('gross_total')->default(0);
            $table->unsignedBigInteger('deduction_total')->default(0);
            $table->bigInteger('net_total')->default(0);
            $table->foreignId('copied_from_id')->nullable()->constrained('payrolls')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->date('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'period']);
            $table->index(['period', 'status']);
        });

        Schema::create('google_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('google_subject')->nullable();
            $table->string('email');
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payslip_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_email');
            $table->string('status', 20);
            $table->string('gmail_message_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 120);
            $table->string('entity_type', 100)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'app_settings', 'payslip_deliveries', 'google_connections', 'payrolls', 'payroll_rules', 'minimum_wages', 'employees'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'active']);
        });
    }
};
