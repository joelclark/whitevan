import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useCallback, useEffect, useRef } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    edit as estimatesEdit,
    index as estimatesIndex,
} from '@/routes/estimates';
import type { EstimateStatus, PaginatedEstimates } from '@/types';

type Filters = {
    search: string;
};

type Props = {
    estimates: PaginatedEstimates;
    filters: Filters;
};

function applyFilters(updates: Partial<Filters>, current: Filters) {
    const merged = { ...current, ...updates };
    const params: Record<string, string> = {};

    if (merged.search) {
        params.search = merged.search;
    }

    router.get(estimatesIndex().url, params, {
        preserveState: true,
        preserveScroll: true,
    });
}

function formatDate(value: string): string {
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

function statusBadge(status: EstimateStatus) {
    switch (status) {
        case 'processing':
            return (
                <Badge variant="secondary" className="animate-pulse">
                    Processing
                </Badge>
            );
        case 'ready':
            return <Badge variant="default">Ready</Badge>;
        case 'failed':
            return <Badge variant="destructive">Failed</Badge>;
    }
}

export default function EstimatesIndex({ estimates, filters }: Props) {
    const searchTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);
    const filtersRef = useRef(filters);

    useEffect(() => {
        filtersRef.current = filters;
    }, [filters]);

    const handleSearch = useCallback((value: string) => {
        if (searchTimeout.current) {
            clearTimeout(searchTimeout.current);
        }

        searchTimeout.current = setTimeout(() => {
            applyFilters({ search: value }, filtersRef.current);
        }, 300);
    }, []);

    const hasEstimates = estimates.data.length > 0;
    const hasSearch = filters.search.length > 0;

    return (
        <>
            <Head title="Estimates" />

            <div>
                <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Estimates"
                        description="Every estimate your team has started, newest first."
                    />
                </div>

                <div className="mb-4">
                    <div className="relative w-full max-w-sm">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Search by title or customer…"
                            defaultValue={filters.search}
                            onChange={(e) => handleSearch(e.target.value)}
                            className="h-12 pl-10 text-base"
                        />
                    </div>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left">
                                <th className="px-4 py-3 font-medium">Title</th>
                                <th className="px-4 py-3 font-medium">
                                    Customer
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Total sqft
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Updated
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {!hasEstimates && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-16 text-center text-muted-foreground"
                                    >
                                        {hasSearch
                                            ? `No estimates match "${filters.search}".`
                                            : 'No estimates yet. Open a customer and upload a floor plan PDF to get started.'}
                                    </td>
                                </tr>
                            )}
                            {estimates.data.map((estimate) => {
                                const customerName =
                                    `${estimate.customer.first_name} ${estimate.customer.last_name}`.trim();

                                return (
                                    <tr
                                        key={estimate.id}
                                        className="min-h-14 cursor-pointer border-b transition-colors last:border-0 hover:bg-muted/50"
                                        onClick={() =>
                                            router.get(
                                                estimatesEdit(estimate.id).url,
                                            )
                                        }
                                    >
                                        <td className="px-4 py-4 font-medium">
                                            <Link
                                                href={estimatesEdit(
                                                    estimate.id,
                                                )}
                                                onClick={(e) =>
                                                    e.stopPropagation()
                                                }
                                                className="hover:underline"
                                            >
                                                {estimate.title ??
                                                    estimate.pdf_original_filename}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-4 text-muted-foreground">
                                            {customerName ||
                                                estimate.customer.company ||
                                                '—'}
                                        </td>
                                        <td className="px-4 py-4 text-muted-foreground">
                                            {estimate.total_sqft !== null
                                                ? `${estimate.total_sqft.toLocaleString()} sq ft`
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-4">
                                            {statusBadge(estimate.status)}
                                        </td>
                                        <td className="px-4 py-4 whitespace-nowrap text-muted-foreground">
                                            {formatDate(estimate.updated_at)}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {estimates.last_page > 1 && (
                    <div className="mt-4 flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Page {estimates.current_page} of{' '}
                            {estimates.last_page} ({estimates.total} total)
                        </span>
                        <div className="flex gap-2">
                            {estimates.prev_page_url ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={estimates.prev_page_url}
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
                            {estimates.next_page_url ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={estimates.next_page_url}
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

EstimatesIndex.layout = {
    breadcrumbs: [{ title: 'Estimates', href: estimatesIndex() }],
};
