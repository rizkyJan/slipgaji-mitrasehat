<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('doctor_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename', 190);
            $table->char('file_sha256', 64);
            $table->unsignedInteger('row_count');
            $table->longText('rows'); // Encrypted by DoctorImportBatch cast.
            $table->json('summary');
            $table->timestamps();
            $table->index(['created_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_import_batches');
    }
};
