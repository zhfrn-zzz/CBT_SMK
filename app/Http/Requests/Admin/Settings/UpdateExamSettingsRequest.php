<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExamSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'default_duration_minutes' => ['required', 'integer', 'min:1', 'max:480'],
            'auto_submit_on_timeout' => ['required', 'boolean'],
            'show_result_after_submit' => ['required', 'boolean'],
            'anti_cheat_enabled' => ['required', 'boolean'],
            'max_tab_switches_default' => ['required', 'integer', 'min:1', 'max:99'],
            'allow_mobile_exam' => ['required', 'boolean'],
            'device_lock_default' => ['required', 'boolean'],
            'watermark_enabled' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'default_duration_minutes.min' => 'Durasi minimal 1 menit.',
            'default_duration_minutes.max' => 'Durasi maksimal 480 menit.',
            'max_tab_switches_default.min' => 'Minimal 1 kali pergantian tab.',
            'max_tab_switches_default.max' => 'Maksimal 99 kali pergantian tab.',
        ];
    }
}
