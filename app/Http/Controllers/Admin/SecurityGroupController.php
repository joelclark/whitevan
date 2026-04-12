<?php

namespace App\Http\Controllers\Admin;

use App\Contexts\AccountContext;
use App\Enums\ActivityEvent;
use App\Enums\SecurityGroup;
use App\Http\Controllers\Controller;
use App\Models\SecurityGroupUser;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SecurityGroupController extends Controller
{
    public function update(
        Request $request,
        User $user,
        AccountContext $accountContext,
    ): RedirectResponse {
        $account = $accountContext->get();

        abort_if($account === null, 403);
        abort_if(! $user->isMemberOf($account), 404);

        // Defense-in-depth: sysops have no account memberships, so the check above
        // already 404s for them. This guard exists in case that invariant changes.
        abort_if($user->isSysop(), 422, 'Sysops cannot have security group memberships.');

        $validated = $request->validate([
            'security_groups' => ['present', 'array'],
            'security_groups.*' => ['required', 'string', Rule::enum(SecurityGroup::class)],
        ]);

        $desired = collect($validated['security_groups']);

        if ($user->id === $request->user()->id && ! $desired->contains(SecurityGroup::Admin->value)) {
            abort(422, 'You cannot remove your own Admin group.');
        }

        $current = $user->securityGroupMemberships()
            ->where('account_id', $account->id)
            ->pluck('security_group')
            ->map(fn (SecurityGroup $group) => $group->value);

        $toAdd = $desired->diff($current);
        $toRemove = $current->diff($desired);

        DB::transaction(function () use ($toAdd, $toRemove, $user, $account, $request) {
            foreach ($toAdd as $group) {
                SecurityGroupUser::firstOrCreate([
                    'account_id' => $account->id,
                    'user_id' => $user->id,
                    'security_group' => $group,
                ]);

                ActivityLogger::event(
                    ActivityEvent::UserSecurityGroupAdded,
                    "Security group added: {$group}",
                    ['security_group' => $group, 'target_user_id' => $user->id, 'target_user_email' => $user->email],
                    $account,
                    $request->user(),
                );
            }

            foreach ($toRemove as $group) {
                $user->securityGroupMemberships()
                    ->where('account_id', $account->id)
                    ->where('security_group', $group)
                    ->delete();

                ActivityLogger::event(
                    ActivityEvent::UserSecurityGroupRemoved,
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
