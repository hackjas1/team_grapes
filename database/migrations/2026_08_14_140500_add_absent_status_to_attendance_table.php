<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change status column to VARCHAR(30) to support 'present', 'late', 'absent', 'manual_override', etc.
        DB::statement("ALTER TABLE `attendance` MODIFY COLUMN `status` VARCHAR(30) NOT NULL DEFAULT 'present'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `attendance` MODIFY COLUMN `status` ENUM('present', 'late', 'manual_override') NOT NULL DEFAULT 'present'");
    }
};
