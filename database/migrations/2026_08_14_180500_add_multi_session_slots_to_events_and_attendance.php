<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->enum('session_type', ['half_day', 'whole_day'])->default('half_day')->after('description');
            $table->timestamp('am_checkin_start_time')->nullable()->after('end_time');
            $table->timestamp('am_checkin_end_time')->nullable()->after('am_checkin_start_time');
            $table->timestamp('am_checkout_start_time')->nullable()->after('am_checkin_end_time');
            $table->timestamp('am_checkout_end_time')->nullable()->after('am_checkout_start_time');
            $table->timestamp('pm_checkin_start_time')->nullable()->after('am_checkout_end_time');
            $table->timestamp('pm_checkin_end_time')->nullable()->after('pm_checkin_start_time');
            $table->timestamp('pm_checkout_start_time')->nullable()->after('pm_checkin_end_time');
            $table->timestamp('pm_checkout_end_time')->nullable()->after('pm_checkout_start_time');
            $table->decimal('fine_per_slot', 8, 2)->nullable()->after('fine_amount');
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->timestamp('am_time_in')->nullable()->after('user_id');
            $table->timestamp('am_time_out')->nullable()->after('am_time_in');
            $table->timestamp('pm_time_in')->nullable()->after('am_time_out');
            $table->timestamp('pm_time_out')->nullable()->after('pm_time_in');
            $table->json('slot_statuses')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropColumn(['am_time_in', 'am_time_out', 'pm_time_in', 'pm_time_out', 'slot_statuses']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'session_type',
                'am_checkin_start_time',
                'am_checkin_end_time',
                'am_checkout_start_time',
                'am_checkout_end_time',
                'pm_checkin_start_time',
                'pm_checkin_end_time',
                'pm_checkout_start_time',
                'pm_checkout_end_time',
                'fine_per_slot'
            ]);
        });
    }
};
