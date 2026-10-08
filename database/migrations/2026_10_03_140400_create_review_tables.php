<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users');
            $table->date('due_date')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'submitted'])->default('pending');
            $table->timestamps();

            $table->unique(['material_id', 'reviewer_id']);
            $table->index(['reviewer_id', 'status']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['draft', 'submitted'])->default('draft');
            $table->text('general_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['review_assignment_id', 'material_version_id']);
        });

        Schema::create('review_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('review_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('anchor_type', ['general', 'page', 'timestamp', 'sco'])->default('general');
            $table->unsignedInteger('page_number')->nullable();
            $table->unsignedInteger('timestamp_seconds')->nullable();
            $table->foreignId('scorm_sco_id')->nullable()->constrained('scorm_scos')->nullOnDelete();
            $table->text('body');
            $table->enum('status', ['open', 'addressed'])->default('open');
            $table->foreignId('addressed_by')->nullable()->constrained('users');
            $table->timestamp('addressed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['material_version_id', 'anchor_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_comments');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('review_assignments');
    }
};
