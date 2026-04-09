<?php

namespace App\Http\Controllers\Sysops;

use App\Enums\SecurityGroup;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\SecurityGroupUser;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SecurityGroupController extends Controller
{
    public function update(Request $request, Account $account, User $user): RedirectResponse
    {
        abort_if($user->account_id !== $account->id, 404);

        // Defense-in-depth: sysops have account_id = null, so the check above
        // already 404s for them. This guard exists in case that invariant changes.
        abort_if($user->isSysop(), 422, 'Sysops cannot have security group memberships.');

        $validated = $request->validate([
            'security_groups' => ['present', 'array'],
            'security_groups.*' => ['required', 'string', Rule::enum(SecurityGroup::class)],
        ]);

        $desired = collect($validated['security_groups']);
        $current = $user->securityGroupMemberships()
            ->pluck('security_group')
            ->map(fn (SecurityGroup $group) => $group->value);

        $toAdd = $desired->diff($current);
        $toRemove = $current->diff($desired);

        DB::transaction(function () use ($toAdd, $toRemove, $user, $account, $request) {
            foreach ($toAdd as $group) {
                SecurityGroupUser::firstOrCreate([
                    'user_id' => $user->id,
                    'security_group' => $group,
                ]);

                ActivityLogger::info(
                    "Security group added: {$group}",
                    ['security_group' => $group, 'target_user_id' => $user->id, 'target_user_email' => $user->email],
                    $account,
                    $request->user(),
                );
            }

            foreach ($toRemove as $group) {
                $user->securityGroupMemberships()
                    ->where('security_group', $group)
                    ->delete();

                ActivityLogger::info(
                    "Security group removed: {$group}",
                    ['security_group' => $group, 'target_user_id' => $user->id, 'target_user_email' => $user->email],
                    $account,
                    $request->user(),
                );
            }
        });

        $user->unsetRelation('securityGroupMemberships');

        return back();
    }
}
