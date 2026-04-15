<?php

namespace App\Http\Controllers;

use App\Contexts\AccountContext;
use App\Enums\ActivityEvent;
use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request, AccountContext $accountContext): Response
    {
        $account = $accountContext->get();
        abort_if($account === null, 403);

        $search = trim((string) $request->query('search', ''));

        $customers = Customer::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where(function (Builder $q) use ($like): void {
                    foreach (['first_name', 'last_name', 'company', 'email', 'phone'] as $column) {
                        $q->orWhereLike($column, $like, caseSensitive: false);
                    }
                });
            })
            ->orderByRaw('last_accessed_at IS NULL')
            ->orderByDesc('last_accessed_at')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('customers/index', [
            'customers' => $customers,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('customers/create');
    }

    public function store(CustomerRequest $request, AccountContext $accountContext): RedirectResponse
    {
        $account = $accountContext->get();
        abort_if($account === null, 403);

        $customer = Customer::create($request->validated());

        ActivityLogger::event(
            ActivityEvent::CustomerCreated,
            metadata: [
                'customer_id' => $customer->id,
                'customer_name' => trim($customer->first_name.' '.$customer->last_name),
            ],
            account: $account,
            user: $request->user(),
        );

        return redirect()
            ->route('customers.edit', $customer)
            ->with('status', 'customer-created');
    }

    public function edit(Customer $customer): Response
    {
        // withoutTimestamps prevents Eloquent from appending updated_at to
        // the UPDATE. "Viewing" should not count as a data change.
        Customer::withoutTimestamps(function () use ($customer): void {
            Customer::query()
                ->whereKey($customer->id)
                ->update(['last_accessed_at' => now()]);
        });

        $customer->last_accessed_at = now();
        $customer->loadCount('estimates');

        return Inertia::render('customers/edit', [
            'customer' => $customer,
        ]);
    }

    public function update(
        CustomerRequest $request,
        Customer $customer,
        AccountContext $accountContext,
    ): RedirectResponse {
        $account = $accountContext->get();
        abort_if($account === null, 403);

        $customer->update($request->validated());

        ActivityLogger::event(
            ActivityEvent::CustomerUpdated,
            metadata: [
                'customer_id' => $customer->id,
                'customer_name' => trim($customer->first_name.' '.$customer->last_name),
            ],
            account: $account,
            user: $request->user(),
        );

        return back()->with('status', 'customer-updated');
    }

    public function destroy(
        Request $request,
        Customer $customer,
        AccountContext $accountContext,
    ): RedirectResponse {
        $account = $accountContext->get();
        abort_if($account === null, 403);

        $customerId = $customer->id;
        $customerName = trim($customer->first_name.' '.$customer->last_name);

        // The estimates FK is restrictOnDelete — let the database enforce the
        // rule and surface a friendly message instead of a 500.
        if ($customer->estimates()->exists()) {
            return back()->with('status', 'customer-has-estimates');
        }

        try {
            $customer->delete();
        } catch (QueryException $e) {
            return back()->with('status', 'customer-has-estimates');
        }

        ActivityLogger::event(
            ActivityEvent::CustomerDeleted,
            metadata: [
                'customer_id' => $customerId,
                'customer_name' => $customerName,
            ],
            account: $account,
            user: $request->user(),
        );

        return redirect()
            ->route('customers.index')
            ->with('status', 'customer-deleted');
    }
}
