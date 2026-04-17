<?php

namespace App\Http\Controllers;

use App\Contexts\AccountContext;
use App\Enums\ActivityEvent;
use App\Http\Requests\ProjectStoreRequest;
use App\Http\Requests\ProjectUpdateRequest;
use App\Models\Customer;
use App\Models\Project;
use App\Services\ActivityLogger;
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
                    $q->whereLike('name', $like, caseSensitive: false)
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

        return redirect()
            ->route('projects.edit', $project)
            ->with('status', 'project-created');
    }

    public function edit(Project $project): Response
    {
        $project->load([
            'customer',
            'estimates' => fn ($q) => $q->orderByDesc('updated_at')->orderByDesc('id'),
        ]);

        return Inertia::render('projects/edit', [
            'project' => $project,
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
