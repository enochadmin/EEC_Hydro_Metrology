<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\ReportQueryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Landing page for reporting: what can be reported on, and what the signed-in
 * user is allowed to export.
 */
class ReportController extends Controller
{
    public function __invoke(Request $request, ReportQueryService $queries): Response
    {
        $user = $request->user();

        return Inertia::render('Reports/Index', [
            'overview' => $queries->overview(),
            'abilities' => [
                'generate' => $user->can('generate reports'),
                'export' => $user->can('export reports'),
                'export_restricted' => $user->can('export restricted reports'),
            ],
        ]);
    }
}
