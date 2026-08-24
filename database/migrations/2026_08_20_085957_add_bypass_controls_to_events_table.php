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
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('bypass_expires_at')->nullable()->after('allow_window_bypass');
            $table->unsignedTinyInteger('bypass_count')->default(0)->after('bypass_expires_at');
            $table->string('bypass_reason')->nullable()->after('bypass_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['bypass_expires_at', 'bypass_count', 'bypass_reason']);
        });
    }
};
