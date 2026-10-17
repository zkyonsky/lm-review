<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('year');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['draft', 'active', 'closed'])->default('draft');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['year', 'status']);
        });

        Schema::create('review_period_training', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_period_id')->constrained('review_periods')->cascadeOnDelete();
            $table->foreignId('training_id')->constrained('trainings')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['review_period_id', 'training_id']);
        });

        Schema::table('review_assignments', function (Blueprint $table) {
            $table->index('material_id', 'review_assignments_material_id_idx');
            $table->dropUnique(['material_id', 'reviewer_id']);
            $table->foreignId('review_period_id')->nullable()->after('id')->constrained('review_periods')->nullOnDelete();
            $table->unique(['material_id', 'reviewer_id', 'review_period_id'], 'mat_rev_period_unique');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->enum('conclusion', ['relevant', 'needs_revision'])->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('conclusion');
        });

        Schema::table('review_assignments', function (Blueprint $table) {
            $table->dropUnique('mat_rev_period_unique');
            $table->dropForeign(['review_period_id']);
            $table->dropColumn('review_period_id');
            $table->unique(['material_id', 'reviewer_id']);
            $table->dropIndex('review_assignments_material_id_idx');
        });

        Schema::dropIfExists('review_period_training');
        Schema::dropIfExists('review_periods');
    }
};
