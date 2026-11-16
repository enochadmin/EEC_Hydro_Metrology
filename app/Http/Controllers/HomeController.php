<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Sends visitors to the right entry point: the dashboard for authenticated
 * users, the login screen for everyone else.
 */
class HomeController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return Auth::check()
            ? redirect()->route('dashboard')
            : redirect()->route('login');
    }
}
