<?php

namespace App\Http\Controllers\Sysops;

use App\Http\Controllers\Controller;
use App\Support\Metrics\ActiveUserMetrics;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('sysops/dashboard', [
            'activeUserSeries' => ActiveUserMetrics::rollingSevenDayWindow(),
        ]);
    }
}
