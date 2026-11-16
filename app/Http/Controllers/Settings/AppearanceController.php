<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AppearanceController extends Controller
{
    /**
     * Display the appearance preferences screen.
     *
     * The selected theme is stored in the browser, so this screen only needs
     * to render; no server-side preference is persisted yet.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Settings/Appearance');
    }
}
