<?php

namespace App\Http\Controllers\Sysops;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ActivityLog::with(['account:id,name', 'user:id,name,email']);

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($term) {
                $q->whereLike('description', $term)
                    ->orWhereHas('user', fn ($q) => $q->whereLike('name', $term))
                    ->orWhereHas('account', fn ($q) => $q->whereLike('name', $term));
            });
        }

        $period = $request->string('period', '24h')->toString();
        $periodHours = match ($period) {
            '1h' => 1,
            '7d' => 168,
            '30d' => 720,
            default => 24,
        };

        $query->where('created_at', '>=', now()->subHours($periodHours));

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('account')) {
            $query->where('account_id', $request->integer('account'));
        }

        $sortDirection = $request->string('sort', 'latest')->toString() === 'oldest' ? 'asc' : 'desc';
        $query->orderBy('created_at', $sortDirection);

        return Inertia::render('sysops/activity-logs/index', [
            'activityLogs' => $query->paginate(50)->withQueryString(),
            'filters' => [
                'search' => $request->string('search', '')->toString(),
                'type' => $request->string('type', '')->toString(),
                'account' => $request->string('account', '')->toString(),
                'sort' => $request->string('sort', 'latest')->toString(),
                'period' => $period,
            ],
            'accounts' => Inertia::optional(fn () => Account::orderBy('name')->get(['id', 'name'])),
        ]);
    }
}
