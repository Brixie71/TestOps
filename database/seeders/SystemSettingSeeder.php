<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SystemSetting::firstOrCreate(
            ['setting_key' => 'email_link_expiration_minutes'],
            ['setting_value' => '60'],
        );
    }
}
