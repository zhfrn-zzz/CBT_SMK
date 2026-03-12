<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function toResponse($request): Response
    {
        /** @var Request $request */
        $user = $request->user();

        $redirect = $user?->dashboardRoute() ?? '/dashboard';

        return $request->wantsJson()
            ? response()->json(['two_factor' => true])
            : redirect()->intended($redirect);
    }
}
