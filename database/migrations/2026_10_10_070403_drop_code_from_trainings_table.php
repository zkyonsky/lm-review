<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS "trainings_code_unique"');
        } else {
            $indexes = collect(DB::select("SHOW INDEX FROM `trainings` WHERE `Key_name` = 'trainings_code_unique'"));
            if ($indexes->isNotEmpty()) {
                Schema::table('trainings', function (Blueprint $table) {
                    $table->dropUnique('trainings_code_unique');
                });
            }
        }

        Schema::table('trainings', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('id');
        });
    }
};
