import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useCallback, useEffect, useRef } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    edit as projectsEdit,
    index as projectsIndex,
} from '@/routes/projects';
import type { PaginatedProjects } from '@/types';
import { buildProjectTitle } from './address';

type Filters = {
    search: string;
};

type Props = {
    projects: PaginatedProjects;
    filters: Filters;
};

function applyFilters(updates: Partial<Filters>, current: Filters) {
    const merged = { ...current, ...updates };
    const params: Record<string, string> = {};

    if (merged.search) {
        params.search = merged.search;
    }

    router.get(projectsIndex().url, params, {
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

export default function ProjectsIndex({ projects, filters }: Props) {
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

    const hasProjects = projects.data.length > 0;
    const hasSearch = filters.search.length > 0;

    return (
        <>
            <Head title="Projects" />

            <div>
                <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Projects"
                        description="Every active project, most recent activity first."
                    />
                </div>

                <div className="mb-4">
                    <div className="relative w-full max-w-sm">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Search by project or customer…"
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
                                <th className="px-4 py-3 font-medium">
                                    Project
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Last activity
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {!hasProjects && (
                                <tr>
                                    <td
                                        colSpan={3}
                                        className="px-4 py-16 text-center text-muted-foreground"
                                    >
                                        {hasSearch
                                            ? `No projects match "${filters.search}".`
                                            : 'No projects yet. Open a customer to create one.'}
                                    </td>
                                </tr>
                            )}
                            {projects.data.map((project) => (
                                <tr
                                    key={project.id}
                                    className="min-h-14 cursor-pointer border-b transition-colors last:border-0 hover:bg-muted/50"
                                    onClick={() =>
                                        router.get(projectsEdit(project.id).url)
                                    }
                                >
                                    <td className="px-4 py-4 font-medium">
                                        <Link
                                            href={projectsEdit(project.id)}
                                            onClick={(e) => e.stopPropagation()}
                                            className="hover:underline"
                                        >
                                            {buildProjectTitle(project)}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-4 whitespace-nowrap text-muted-foreground">
                                        {formatDate(project.last_activity_at)}
                                    </td>
                                    <td className="px-4 py-4 text-muted-foreground">
                                        —
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {projects.last_page > 1 && (
                    <div className="mt-4 flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Page {projects.current_page} of {projects.last_page}{' '}
                            ({projects.total} total)
                        </span>
                        <div className="flex gap-2">
                            {projects.prev_page_url ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={projects.prev_page_url}
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
                            {projects.next_page_url ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={projects.next_page_url}
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

ProjectsIndex.layout = {
    breadcrumbs: [{ title: 'Projects', href: projectsIndex() }],
};
