<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dateTime('checkin_start_time')->nullable()->after('end_time');
            $table->dateTime('checkin_end_time')->nullable()->after('checkin_start_time');
            $table->dateTime('checkout_start_time')->nullable()->after('checkin_end_time');
            $table->dateTime('checkout_end_time')->nullable()->after('checkout_start_time');
            $table->boolean('allow_window_bypass')->default(false)->after('checkout_end_time');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'checkin_start_time',
                'checkin_end_time',
                'checkout_start_time',
                'checkout_end_time',
                'allow_window_bypass'
            ]);
        });
    }
};
