<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class BackupController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Artisan::queue('backup:database');

        return back()->with('success', 'Backup dijadwalkan.');
    }
}
