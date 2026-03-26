<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppearanceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // FormData sends booleans as '1'/'0' strings — cast explicitly
        if ($this->has('show_powered_by')) {
            $this->merge([
                'show_powered_by' => filter_var($this->input('show_powered_by'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
            'logo_small' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,ico', 'max:1024'],
            'primary_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'login_bg_type' => ['required', 'string', 'in:color,image'],
            'login_bg_value' => ['nullable', 'string', 'max:255'],
            'footer_text' => ['nullable', 'string', 'max:500'],
            'show_powered_by' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'logo.image' => 'Logo harus berupa file gambar.',
            'logo.max' => 'Ukuran logo maksimal 2MB.',
            'logo_small.image' => 'Logo kecil harus berupa file gambar.',
            'primary_color.regex' => 'Format warna harus #RRGGBB.',
            'secondary_color.regex' => 'Format warna harus #RRGGBB.',
        ];
    }
}
