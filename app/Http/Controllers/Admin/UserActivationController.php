<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserActivationController extends Controller
{
    public function update(Request $request, User $user): RedirectResponse
    {
        $account = $request->user()->account;

        abort_if($account === null, 403);
        abort_if($user->account_id !== $account->id, 404);

        // Defense-in-depth: sysops have account_id = null, so the check above
        // already 404s for them. This guard exists in case that invariant changes.
        abort_if($user->isSysop(), 422, 'Sysop activation is managed separately.');

        abort_if($user->id === $request->user()->id, 422, 'You cannot deactivate yourself.');

        $validated = $request->validate([
            'deactivated' => ['required', 'boolean'],
        ]);

        $isDeactivating = $validated['deactivated'];
        $isCurrentlyDeactivated = $user->isDeactivated();

        if ($isDeactivating === $isCurrentlyDeactivated) {
            return back();
        }

        $user->forceFill([
            'deactivated_at' => $isDeactivating ? now() : null,
        ])->save();

        $action = $isDeactivating ? 'User deactivated' : 'User activated';

        ActivityLogger::info(
            $action,
            [
                'target_user_id' => $user->id,
                'target_user_email' => $user->email,
            ],
            $account,
            $request->user(),
        );

        return back();
    }
}
