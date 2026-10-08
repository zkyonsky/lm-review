<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scorm_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_version_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('scorm_version', ['1.2', '2004'])->nullable();
            $table->string('identifier')->nullable();
            $table->string('title')->nullable();
            $table->string('extract_path', 500)->nullable();
            $table->string('launch_path', 500)->nullable();
            $table->enum('status', ['processing', 'ready', 'failed'])->default('processing');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('scorm_scos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scorm_package_id')->constrained()->cascadeOnDelete();
            $table->string('identifier');
            $table->string('title');
            $table->string('launch_path', 500);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scorm_scos');
        Schema::dropIfExists('scorm_packages');
    }
};
