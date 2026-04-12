<?php

namespace App\Http\Controllers\Sysops;

use App\Enums\SecurityGroup;
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

    public function show(Account $account): Response
    {
        $account->load([
            'owner:id,name,email',
            'users' => fn ($query) => $query->select('users.id', 'users.name', 'users.email', 'users.deactivated_at', 'users.created_at')->with('securityGroupMemberships')->orderBy('users.name'),
        ]);

        return Inertia::render('sysops/accounts/show', [
            'account' => $account,
            'securityGroups' => SecurityGroup::toArray(),
        ]);
    }
}
