<?php

namespace App\Http\Controllers\Admin;

use App\Contexts\AccountContext;
use App\Enums\SecurityGroup;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(AccountContext $accountContext): Response
    {
        $account = $accountContext->get();

        abort_if($account === null, 403);

        $users = $account->users()
            ->select('id', 'account_id', 'name', 'email', 'deactivated_at', 'created_at')
            ->with('securityGroupMemberships')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'securityGroups' => SecurityGroup::toArray(),
        ]);
    }
}
