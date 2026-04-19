<?php

namespace App\Services;

use App\Contexts\ImpersonationContext;
use App\Enums\ActivityEvent;
use App\Enums\ActorType;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\ProjectEvent;
use App\Models\User;

/**
 * Writes a row to `project_events` for the contractor (and eventually
 * customer) timeline view on a project.
 *
 * This is a deliberate companion to ActivityLogger, not a replacement.
 * ActivityLogger is the sysop-facing global audit log with a loose JSON
 * metadata blob. ProjectEventLogger is a narrow, column-structured feed
 * scoped to a project, with a customer-visibility flag derived from the
 * event itself.
 *
 * Call sites should continue to call ActivityLogger::event() alongside
 * ProjectEventLogger::record() — each audience wants its own shape.
 */
class ProjectEventLogger
{
    /**
     * @param  Project|Estimate  $subject  Scopes the event to a project (directly
     *                                     for Project, via $estimate->project for
     *                                     Estimate). `estimate_id` is populated
     *                                     automatically when the subject is an
     *                                     Estimate.
     * @param  ActorType|null  $actorType  Explicit override. When null, defaults
     *                                     to Contractor if `$user` is set, else
     *                                     System. Guest routes (quote view,
     *                                     approval flow) must pass Customer.
     * @param  array<string, mixed>|null  $metadata  Long-tail context (IP,
     *                                               user-agent, is_first_view, etc.).
     *                                               Important bits live in real columns —
     *                                               use this only for the rest.
     */
    public static function record(
        Project|Estimate $subject,
        ActivityEvent $event,
        ?User $user = null,
        ?array $metadata = null,
        ?ActorType $actorType = null,
    ): void {
        $project = $subject instanceof Estimate ? $subject->project : $subject;
        $estimateId = $subject instanceof Estimate ? $subject->id : null;

        // Mirror of the impersonation invariant in ActivityLogger::record —
        // keep the two loggers in sync when the invariant changes. The
        // duplication is deliberate; collapse into a shared helper if a
        // third logger appears.
        if (app(ImpersonationContext::class)->isImpersonating()) {
            $metadata = array_merge($metadata ?? [], ['impersonated' => true]);
        }

        $resolvedActorType = $actorType ?? ($user !== null ? ActorType::Contractor : ActorType::System);

        ProjectEvent::create([
            // Set explicitly so guest-route callers (which have no
            // AccountContext) don't depend on the BelongsToAccount
            // auto-fill hook.
            'account_id' => $project->account_id,
            'project_id' => $project->id,
            'estimate_id' => $estimateId,
            'event' => $event,
            'user_id' => $user?->id,
            'actor_type' => $resolvedActorType,
            'customer_visible' => $event->isCustomerVisible(),
            'metadata' => $metadata,
        ]);
    }
}
