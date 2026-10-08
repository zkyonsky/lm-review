<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['pdf', 'video', 'scorm']);
            $table->enum('status', ['draft', 'in_review', 'reviewed', 'revising', 'completed'])->default('draft');
            // FK ke material_versions ditambahkan setelah tabel tersebut dibuat (relasi melingkar).
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['subject_id', 'sort_order']);
            $table->index('status');
        });

        Schema::create('material_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('gdrive_url', 500)->nullable();
            $table->string('gdrive_file_id', 100)->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('original_filename')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('changelog')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['material_id', 'version_number']);
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('material_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('material_versions');
        Schema::dropIfExists('materials');
    }
};
