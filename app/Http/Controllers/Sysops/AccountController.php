<?php

namespace App\Http\Controllers\Sysops;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('sysops/accounts/index', [
            'accounts' => Account::with('owner:id,name,email')
                ->orderBy('name')
                ->paginate(25),
        ]);
    }
}
