import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import Heading from '@/components/heading';
import { SearchInput } from '@/components/search-input';
import { Button } from '@/components/ui/button';
import {
    create as customersCreate,
    edit as customersEdit,
    index as customersIndex,
} from '@/routes/customers';
import type { PaginatedCustomers } from '@/types';

type Filters = {
    search: string;
};

type Props = {
    customers: PaginatedCustomers;
    filters: Filters;
};

function applyFilters(updates: Partial<Filters>, current: Filters) {
    const merged = { ...current, ...updates };
    const params: Record<string, string> = {};

    if (merged.search) {
        params.search = merged.search;
    }

    router.get(customersIndex().url, params, {
        preserveState: true,
        preserveScroll: true,
    });
}

function formatLastViewed(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

export default function CustomersIndex({ customers, filters }: Props) {
    const hasCustomers = customers.data.length > 0;
    const hasSearch = filters.search.length > 0;

    return (
        <>
            <Head title="Customers" />

            <div>
                <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Customers"
                        description="Manage your customer records."
                    />
                    <Button asChild>
                        <Link href={customersCreate()}>
                            <Plus className="mr-1 h-4 w-4" />
                            Add customer
                        </Link>
                    </Button>
                </div>

                <div className="mb-4">
                    <SearchInput
                        value={filters.search}
                        onSearch={(search) => applyFilters({ search }, filters)}
                        placeholder="Search by name, company, email, phone…"
                        icon={
                            <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        }
                        className="pl-9"
                        wrapperClassName="w-full max-w-sm"
                    />
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left">
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">
                                    Company
                                </th>
                                <th className="px-4 py-3 font-medium">Email</th>
                                <th className="px-4 py-3 font-medium">Phone</th>
                                <th className="px-4 py-3 font-medium">
                                    Last viewed
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {!hasCustomers && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        {hasSearch
                                            ? `No customers match "${filters.search}".`
                                            : 'No customers yet. Add your first one to get started.'}
                                    </td>
                                </tr>
                            )}
                            {customers.data.map((customer) => {
                                const fullName =
                                    `${customer.first_name} ${customer.last_name}`.trim();

                                return (
                                    <tr
                                        key={customer.id}
                                        className="cursor-pointer border-b transition-colors last:border-0 hover:bg-muted/50"
                                        onClick={() =>
                                            router.get(
                                                customersEdit(customer.id).url,
                                            )
                                        }
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            <Link
                                                href={customersEdit(
                                                    customer.id,
                                                )}
                                                onClick={(e) =>
                                                    e.stopPropagation()
                                                }
                                                className="hover:underline"
                                            >
                                                {fullName}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {customer.company ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {customer.email ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {customer.phone ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                            {formatLastViewed(
                                                customer.last_accessed_at,
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {customers.last_page > 1 && (
                    <div className="mt-4 flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Page {customers.current_page} of{' '}
                            {customers.last_page} ({customers.total} total)
                        </span>
                        <div className="flex gap-2">
                            {customers.prev_page_url ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={customers.prev_page_url}
                                        preserveState
                                    >
                                        Previous
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" size="sm" disabled>
                                    Previous
                                </Button>
                            )}
                            {customers.next_page_url ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={customers.next_page_url}
                                        preserveState
                                    >
                                        Next
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" size="sm" disabled>
                                    Next
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

CustomersIndex.layout = {
    breadcrumbs: [{ title: 'Customers', href: customersIndex() }],
};
