<?php

namespace App\Http\Controllers;

use App\Contexts\AccountContext;
use App\Enums\ActivityEvent;
use App\Http\Requests\ProjectStoreRequest;
use App\Http\Requests\ProjectUpdateRequest;
use App\Models\Customer;
use App\Models\Project;
use App\Services\ActivityLogger;
use App\Services\ProjectEventLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request, AccountContext $accountContext): Response
    {
        $account = $accountContext->get();
        abort_if($account === null, 403);

        $search = trim((string) $request->query('search', ''));

        $projects = Project::query()
            ->with(['customer:id,first_name,last_name,company,address_line_1'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where(function (Builder $q) use ($like): void {
                    // Matches the fields that appear in the project's long
                    // title: name / customer.last_name / resolved street.
                    // Street resolution mirrors resolveProjectAddress() on the
                    // frontend: the site override wins when set, otherwise the
                    // customer's default address is what renders. first_name
                    // and company aren't in the title but stay searchable as
                    // "how humans refer to the customer".
                    $q->whereLike('name', $like, caseSensitive: false)
                        ->orWhereLike('site_address_line_1', $like, caseSensitive: false)
                        ->orWhere(function (Builder $inherited) use ($like): void {
                            $inherited->whereNull('site_address_line_1')
                                ->whereHas('customer', function (Builder $cq) use ($like): void {
                                    $cq->whereLike('address_line_1', $like, caseSensitive: false);
                                });
                        })
                        ->orWhereHas('customer', function (Builder $cq) use ($like): void {
                            $cq->where(function (Builder $inner) use ($like): void {
                                $inner->whereLike('first_name', $like, caseSensitive: false)
                                    ->orWhereLike('last_name', $like, caseSensitive: false)
                                    ->orWhereLike('company', $like, caseSensitive: false);
                            });
                        });
                });
            })
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('projects/index', [
            'projects' => $projects,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function store(
        ProjectStoreRequest $request,
        Customer $customer,
        AccountContext $accountContext,
    ): RedirectResponse {
        $account = $accountContext->get();
        abort_if($account === null, 403);

        $project = $customer->projects()->create($request->validated());

        ActivityLogger::event(
            ActivityEvent::ProjectCreated,
            metadata: [
                'project_id' => $project->id,
                'customer_id' => $customer->id,
                'project_name' => $project->name,
            ],
            account: $account,
            user: $request->user(),
        );

        ProjectEventLogger::record(
            $project,
            ActivityEvent::ProjectCreated,
            user: $request->user(),
            metadata: ['project_name' => $project->name],
        );

        return redirect()
            ->route('projects.edit', $project)
            ->with('status', 'project-created');
    }

    public function edit(Project $project): Response
    {
        $project->load([
            'customer',
            'estimates' => fn ($q) => $q->orderByDesc('updated_at')->orderByDesc('id'),
            'events' => fn ($q) => $q->orderByDesc('created_at')->orderByDesc('id'),
            'events.user:id,name,email',
            'events.estimate:id,title,pdf_original_filename,deleted_at',
        ]);

        // Flatten events into the shape the frontend consumes. Shipped as a
        // sibling prop (matches the `floorplan_pages` / `line_items`
        // convention on EstimateController::edit) so `Project.events` stays
        // off the Inertia type and the frontend isn't tempted to reach into
        // raw model relations.
        $events = $project->events->map(function ($event) {
            $estimate = $event->estimate;
            $user = $event->user;

            return [
                'id' => $event->id,
                'event' => $event->event->value,
                'event_label' => $event->event->label(),
                'actor_type' => $event->actor_type->value,
                'actor_name' => $user?->name,
                'estimate_id' => $event->estimate_id,
                'estimate_title' => $estimate?->title ?? $estimate?->pdf_original_filename,
                // Timeline rows survive estimate soft-deletes, but the edit
                // route does not — flag deleted rows so the frontend renders
                // them as read-only history instead of broken links.
                'estimate_deleted' => $estimate !== null && $estimate->trashed(),
                'metadata' => $event->metadata,
                'created_at' => $event->created_at->toIso8601String(),
            ];
        })->values()->all();

        return Inertia::render('projects/edit', [
            'project' => $project,
            'events' => $events,
        ]);
    }

    public function update(
        ProjectUpdateRequest $request,
        Project $project,
        AccountContext $accountContext,
    ): RedirectResponse {
        $account = $accountContext->get();
        abort_if($account === null, 403);

        $project->update($request->validated());
        $project->recordActivity();

        ActivityLogger::event(
            ActivityEvent::ProjectUpdated,
            metadata: [
                'project_id' => $project->id,
                'customer_id' => $project->customer_id,
                'project_name' => $project->name,
            ],
            account: $account,
            user: $request->user(),
        );

        ProjectEventLogger::record(
            $project,
            ActivityEvent::ProjectUpdated,
            user: $request->user(),
            metadata: ['project_name' => $project->name],
        );

        return back()->with('status', 'project-updated');
    }

    public function destroy(
        Request $request,
        Project $project,
        AccountContext $accountContext,
    ): RedirectResponse {
        $account = $accountContext->get();
        abort_if($account === null, 403);

        $projectId = $project->id;
        $customerId = $project->customer_id;
        $projectName = $project->name;

        try {
            $project->delete();
        } catch (QueryException $e) {
            return back()->with('status', 'project-delete-failed');
        }

        ProjectEventLogger::record(
            $project,
            ActivityEvent::ProjectDeleted,
            user: $request->user(),
            metadata: ['project_name' => $projectName],
        );

        ActivityLogger::event(
            ActivityEvent::ProjectDeleted,
            metadata: [
                'project_id' => $projectId,
                'customer_id' => $customerId,
                'project_name' => $projectName,
            ],
            account: $account,
            user: $request->user(),
        );

        return redirect()
            ->route('customers.edit', $customerId)
            ->with('status', 'project-deleted');
    }
}
