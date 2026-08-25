<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'institutional_email_domain',
                'value' => 'tpc.edu.ph',
                'description' => 'Required institutional email domain for student accounts',
            ],
            [
                'key' => 'qr_expiration_seconds',
                'value' => '60',
                'description' => 'Dynamic QR code token expiration time in seconds',
            ],
            [
                'key' => 'default_allowed_radius_meters',
                'value' => '50',
                'description' => 'Default GPS verification radius in meters for events',
            ],
            [
                'key' => 'college_name',
                'value' => 'Talibon Polytechnic College',
                'description' => 'Name of the institution',
            ],
            [
                'key' => 'department_name',
                'value' => 'Bachelor of Science in Information Systems (BSIS)',
                'description' => 'Academic department name',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
