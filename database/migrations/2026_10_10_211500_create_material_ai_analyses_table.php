<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_version_id')->constrained('material_versions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('model', 50)->default('gemini-3.8-flash');
            $table->string('status', 30)->default('completed');
            $table->text('summary')->nullable();
            $table->json('identified_regulations')->nullable();
            $table->json('recommendations')->nullable();
            $table->string('suggested_conclusion')->nullable();
            $table->text('conclusion_rationale')->nullable();
            $table->json('raw_response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_ai_analyses');
    }
};
