<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->string('status', 50)->default('Sedang Review')->change();
        });

        DB::table('trainings')
            ->whereIn('status', ['active', 'archived'])
            ->orWhereNull('status')
            ->update(['status' => 'Sedang Review']);
    }

    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->string('status', 50)->default('active')->change();
        });
    }
};
