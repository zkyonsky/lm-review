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
        Schema::table('subjects', function (Blueprint $table) {
            if (Schema::hasColumn('subjects', 'code')) {
                $table->dropColumn('code');
            }
            if (Schema::hasColumn('subjects', 'jp')) {
                $table->dropColumn('jp');
            }
        });

        Schema::table('materials', function (Blueprint $table) {
            if (Schema::hasColumn('materials', 'title')) {
                $table->dropColumn('title');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('code', 50)->nullable();
            $table->unsignedSmallInteger('jp')->nullable();
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->string('title')->default('Materi');
        });
    }
};
