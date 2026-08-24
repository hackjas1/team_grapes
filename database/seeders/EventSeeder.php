<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();
        $staff = User::where('role', 'event_staff')->first();

        if ($admin) {
            $event = Event::updateOrCreate(
                ['title' => 'BSIS General Assembly & Tech Summit 2026'],
                [
                    'uuid' => (string) Str::uuid(),
                    'description' => 'Mandatory departmental assembly for all BSIS students at Talibon Polytechnic College.',
                    'start_time' => now()->addHour(),
                    'end_time' => now()->addHours(5),
                    'venue_name' => 'TPC Main Cultural Center Auditorium',
                    'venue_latitude' => 10.14920000,
                    'venue_longitude' => 124.33120000,
                    'allowed_radius_meters' => 50.00,
                    'fine_amount' => 50.00,
                    'status' => 'active',
                    'created_by' => $admin->id,
                ]
            );

            if ($staff && !$event->staff()->where('user_id', $staff->id)->exists()) {
                $event->staff()->attach($staff->id);
            }
        }
    }
}
