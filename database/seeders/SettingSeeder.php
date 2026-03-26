<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General
            ['group' => 'general', 'key' => 'app_name', 'value' => 'SMK LMS', 'type' => 'string'],
            ['group' => 'general', 'key' => 'school_name', 'value' => 'SMK Bina Mandiri', 'type' => 'string'],
            ['group' => 'general', 'key' => 'school_address', 'value' => '', 'type' => 'string'],
            ['group' => 'general', 'key' => 'school_phone', 'value' => '', 'type' => 'string'],
            ['group' => 'general', 'key' => 'school_email', 'value' => '', 'type' => 'string'],
            ['group' => 'general', 'key' => 'school_website', 'value' => '', 'type' => 'string'],
            ['group' => 'general', 'key' => 'school_tagline', 'value' => 'Mencetak Generasi Mandiri dan Berkarakter', 'type' => 'string'],

            // Appearance
            ['group' => 'appearance', 'key' => 'logo_path', 'value' => 'images/logo.png', 'type' => 'file'],
            ['group' => 'appearance', 'key' => 'logo_small_path', 'value' => '', 'type' => 'file'],
            ['group' => 'appearance', 'key' => 'primary_color', 'value' => '#2563eb', 'type' => 'string'],
            ['group' => 'appearance', 'key' => 'secondary_color', 'value' => '#64748b', 'type' => 'string'],
            ['group' => 'appearance', 'key' => 'login_bg_type', 'value' => 'color', 'type' => 'string'],
            ['group' => 'appearance', 'key' => 'login_bg_value', 'value' => '#f8fafc', 'type' => 'string'],
            ['group' => 'appearance', 'key' => 'footer_text', 'value' => '', 'type' => 'string'],
            ['group' => 'appearance', 'key' => 'show_powered_by', 'value' => 'true', 'type' => 'boolean'],

            // Exam
            ['group' => 'exam', 'key' => 'default_duration_minutes', 'value' => '60', 'type' => 'integer'],
            ['group' => 'exam', 'key' => 'auto_submit_on_timeout', 'value' => 'true', 'type' => 'boolean'],
            ['group' => 'exam', 'key' => 'show_result_after_submit', 'value' => 'false', 'type' => 'boolean'],
            ['group' => 'exam', 'key' => 'anti_cheat_enabled', 'value' => 'true', 'type' => 'boolean'],
            ['group' => 'exam', 'key' => 'max_tab_switches_default', 'value' => '3', 'type' => 'integer'],
            ['group' => 'exam', 'key' => 'allow_mobile_exam', 'value' => 'false', 'type' => 'boolean'],
            ['group' => 'exam', 'key' => 'device_lock_default', 'value' => 'true', 'type' => 'boolean'],
            ['group' => 'exam', 'key' => 'watermark_enabled', 'value' => 'true', 'type' => 'boolean'],

            // Email
            ['group' => 'email', 'key' => 'smtp_host', 'value' => '', 'type' => 'string'],
            ['group' => 'email', 'key' => 'smtp_port', 'value' => '587', 'type' => 'integer'],
            ['group' => 'email', 'key' => 'smtp_username', 'value' => '', 'type' => 'string'],
            ['group' => 'email', 'key' => 'smtp_password', 'value' => '', 'type' => 'string'],
            ['group' => 'email', 'key' => 'smtp_encryption', 'value' => 'tls', 'type' => 'string'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                $setting,
            );
        }
    }
}
