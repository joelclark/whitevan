import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { SearchInput } from '@/components/search-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as sysopsAccountsIndex } from '@/routes/sysops/accounts';
import { index as activityLogsIndex } from '@/routes/sysops/activity-logs';
import type { Account, ActivityLog } from '@/types';

type PaginatedActivityLogs = {
    data: ActivityLog[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Filters = {
    search: string;
    type: string;
    account: string;
    sort: string;
    period: string;
};

type Props = {
    activityLogs: PaginatedActivityLogs;
    filters: Filters;
    accounts?: Pick<Account, 'id' | 'name'>[];
};

function applyFilters(updates: Partial<Filters>, current: Filters) {
    const merged = { ...current, ...updates };
    const params: Record<string, string> = {};

    if (merged.search) {
        params.search = merged.search;
    }

    if (merged.type) {
        params.type = merged.type;
    }

    if (merged.account) {
        params.account = merged.account;
    }

    if (merged.sort && merged.sort !== 'latest') {
        params.sort = merged.sort;
    }

    if (merged.period && merged.period !== '24h') {
        params.period = merged.period;
    }

    router.get(activityLogsIndex().url, params, {
        preserveState: true,
        preserveScroll: true,
    });
}

export default function ActivityLogsIndex({
    activityLogs,
    filters,
    accounts,
}: Props) {
    setLayoutProps({
        title: 'Activity Logs',
        description: 'View system-wide activity and events',
    });

    const filtersRef = useRef(filters);

    useEffect(() => {
        filtersRef.current = filters;
    }, [filters]);

    return (
        <>
            <Head title="Activity Log" />

            <div className="mb-4 flex flex-wrap items-center gap-3">
                <SearchInput
                    value={filters.search}
                    onSearch={(search) =>
                        applyFilters({ search }, filtersRef.current)
                    }
                    placeholder="Search..."
                    wrapperClassName="w-64"
                />

                <Select
                    value={filters.type || 'all'}
                    onValueChange={(value) =>
                        applyFilters(
                            { type: value === 'all' ? '' : value },
                            filters,
                        )
                    }
                >
                    <SelectTrigger className="w-36">
                        <SelectValue placeholder="All types" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All types</SelectItem>
                        <SelectItem value="info">Info</SelectItem>
                        <SelectItem value="error">Error</SelectItem>
                    </SelectContent>
                </Select>

                {accounts && (
                    <Select
                        value={filters.account || 'all'}
                        onValueChange={(value) =>
                            applyFilters(
                                { account: value === 'all' ? '' : value },
                                filters,
                            )
                        }
                    >
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="All accounts" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All accounts</SelectItem>
                            {accounts.map((account) => (
                                <SelectItem
                                    key={account.id}
                                    value={String(account.id)}
                                >
                                    {account.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                )}

                <Select
                    value={filters.period || '24h'}
                    onValueChange={(value) =>
                        applyFilters({ period: value }, filters)
                    }
                >
                    <SelectTrigger className="w-36">
                        <SelectValue placeholder="Last 24 hours" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="1h">Last hour</SelectItem>
                        <SelectItem value="24h">Last 24 hours</SelectItem>
                        <SelectItem value="7d">Last 7 days</SelectItem>
                        <SelectItem value="30d">Last 30 days</SelectItem>
                    </SelectContent>
                </Select>

                <Button
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        applyFilters(
                            {
                                sort:
                                    filters.sort === 'oldest'
                                        ? 'latest'
                                        : 'oldest',
                            },
                            filters,
                        )
                    }
                >
                    {filters.sort === 'oldest'
                        ? 'Oldest first'
                        : 'Newest first'}
                </Button>
            </div>

            <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b text-left">
                            <th className="px-4 py-3 font-medium">Date</th>
                            <th className="px-4 py-3 font-medium">Type</th>
                            <th className="px-4 py-3 font-medium">
                                Description
                            </th>
                            <th className="px-4 py-3 font-medium">User</th>
                            <th className="px-4 py-3 font-medium">Account</th>
                        </tr>
                    </thead>
                    <tbody>
                        {activityLogs.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="px-4 py-8 text-center text-muted-foreground"
                                >
                                    No activity logs found.
                                </td>
                            </tr>
                        )}
                        {activityLogs.data.map((log) => (
                            <tr key={log.id} className="border-b last:border-0">
                                <td className="px-4 py-3 whitespace-nowrap">
                                    {new Date(log.created_at).toLocaleString()}
                                </td>
                                <td className="px-4 py-3">
                                    <Badge
                                        variant={
                                            log.type === 'error'
                                                ? 'destructive'
                                                : 'secondary'
                                        }
                                    >
                                        {log.type === 'error'
                                            ? 'Error'
                                            : 'Info'}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3">
                                    {log.description}
                                    {log.metadata &&
                                        Object.keys(log.metadata).length >
                                            0 && (
                                            <div className="mt-1 flex flex-wrap gap-x-3 text-xs text-muted-foreground">
                                                {Object.entries(
                                                    log.metadata,
                                                ).map(([key, value]) => (
                                                    <span key={key}>
                                                        {key}: {String(value)}
                                                    </span>
                                                ))}
                                            </div>
                                        )}
                                </td>
                                <td className="px-4 py-3">
                                    {log.user?.name ?? log.user?.email ?? '—'}
                                </td>
                                <td className="px-4 py-3">
                                    {log.account?.name ?? '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {activityLogs.last_page > 1 && (
                <div className="mt-4 flex items-center justify-between text-sm">
                    <span className="text-muted-foreground">
                        Page {activityLogs.current_page} of{' '}
                        {activityLogs.last_page} ({activityLogs.total} total)
                    </span>
                    <div className="flex gap-2">
                        {activityLogs.prev_page_url ? (
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={activityLogs.prev_page_url}
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
                        {activityLogs.next_page_url ? (
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={activityLogs.next_page_url}
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
        </>
    );
}

ActivityLogsIndex.layout = {
    breadcrumbs: [
        { title: 'Sysops', href: sysopsAccountsIndex().url },
        { title: 'Activity Log', href: activityLogsIndex().url },
    ],
};
