<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateAppearanceSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateEmailSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateExamSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateGeneralSettingsRequest;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function __construct(
        private readonly SettingService $settingService,
    ) {}

    public function index(): Response
    {
        $emailSettings = $this->settingService->getByGroup('email');
        // Mask SMTP password for frontend display
        if (! empty($emailSettings['smtp_password'])) {
            $emailSettings['smtp_password'] = '********';
        }

        return Inertia::render('Admin/Settings/Index', [
            'settings' => [
                'general' => $this->settingService->getByGroup('general'),
                'appearance' => $this->settingService->getByGroup('appearance'),
                'exam' => $this->settingService->getByGroup('exam'),
                'email' => $emailSettings,
            ],
        ]);
    }

    public function updateGeneral(UpdateGeneralSettingsRequest $request): RedirectResponse
    {
        $this->settingService->setMany($request->validated());

        return back()->with('success', 'Pengaturan umum berhasil disimpan.');
    }

    public function updateAppearance(UpdateAppearanceSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $this->settingService->handleFileUpload('logo_path', $request->file('logo'));
        }

        if ($request->hasFile('logo_small')) {
            $this->settingService->handleFileUpload('logo_small_path', $request->file('logo_small'));
        }

        // Save non-file settings
        $nonFileSettings = array_diff_key($validated, array_flip(['logo', 'logo_small']));
        $this->settingService->setMany($nonFileSettings);

        return back()->with('success', 'Pengaturan tampilan berhasil disimpan.');
    }

    public function updateExam(UpdateExamSettingsRequest $request): RedirectResponse
    {
        $this->settingService->setMany($request->validated());

        return back()->with('success', 'Pengaturan ujian berhasil disimpan.');
    }

    public function updateEmail(UpdateEmailSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Don't overwrite password with mask value
        if ($validated['smtp_password'] === '********' || $validated['smtp_password'] === null) {
            unset($validated['smtp_password']);
        }

        $this->settingService->setMany($validated);

        return back()->with('success', 'Pengaturan email berhasil disimpan.');
    }
}
