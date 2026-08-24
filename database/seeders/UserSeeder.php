<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Device;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. System Administrator
        User::updateOrCreate(
            ['email' => 'admin@tpc.edu.ph'],
            [
                'uuid' => (string) Str::uuid(),
                'student_number' => null,
                'first_name' => 'System',
                'middle_name' => 'BSIS',
                'last_name' => 'Administrator',
                'email' => 'admin@tpc.edu.ph',
                'password' => Hash::make('Password123!'),
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        // 2. Event Staff
        $staff = User::updateOrCreate(
            ['email' => 'staff@tpc.edu.ph'],
            [
                'uuid' => (string) Str::uuid(),
                'student_number' => null,
                'first_name' => 'Event',
                'middle_name' => 'Officer',
                'last_name' => 'Staff',
                'email' => 'staff@tpc.edu.ph',
                'password' => Hash::make('Password123!'),
                'role' => 'event_staff',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        // 3. Sample Active Student
        $student = User::updateOrCreate(
            ['email' => 'student1@tpc.edu.ph'],
            [
                'uuid' => (string) Str::uuid(),
                'student_number' => '2024-00001',
                'first_name' => 'Christian Paul',
                'middle_name' => 'G.',
                'last_name' => 'La Rosa',
                'email' => 'student1@tpc.edu.ph',
                'password' => Hash::make('Password123!'),
                'role' => 'student',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        // Seed initial device binding for test student
        Device::updateOrCreate(
            ['user_id' => $student->id],
            [
                'device_credential' => (string) Str::uuid(),
                'device_name' => 'Primary Authorized Mobile Browser',
                'user_agent' => 'Mozilla/5.0 (Android; Mobile)',
                'ip_address' => '127.0.0.1',
                'status' => 'active',
                'bound_at' => now(),
            ]
        );
    }
}
