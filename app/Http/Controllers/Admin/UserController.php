<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SecurityGroup;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $account = $request->user()->account;

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
