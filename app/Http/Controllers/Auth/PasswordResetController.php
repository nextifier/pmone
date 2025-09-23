<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PasswordResetController extends Controller
{
    /**
     * Display the password reset form.
     */
    public function showResetForm(Request $request, string $token): RedirectResponse
    {
        $email = $request->get('email');

        // Redirect to frontend with token and email
        $frontendUrl = config('app.frontend_url').'/reset-password';
        $queryParams = http_build_query([
            'token' => $token,
            'email' => $email,
        ]);

        return redirect()->to($frontendUrl.'?'.$queryParams);
    }
}
