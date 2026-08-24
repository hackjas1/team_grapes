<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('year_level', 20)->nullable()->after('role');
            $table->string('section_block', 50)->nullable()->after('year_level');

            $table->index(['year_level', 'section_block']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['year_level', 'section_block']);
            $table->dropColumn(['year_level', 'section_block']);
        });
    }
};
