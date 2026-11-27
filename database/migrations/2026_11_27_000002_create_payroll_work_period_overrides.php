<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payroll_work_period_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('person_kind', 12);
            $table->unsignedBigInteger('person_id');
            $table->string('period', 7);
            $table->date('work_from');
            $table->date('work_to');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['person_kind', 'person_id', 'period'], 'period_override_person_month_unique');
            $table->index(['period', 'person_kind'], 'period_override_month_kind_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_work_period_overrides');
    }
};
