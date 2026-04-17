import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit as estimatesEdit } from '@/routes/estimates';
import { index as projectsIndex } from '@/routes/projects';
import type { Estimate, EstimateStatus, Project } from '@/types';
import ProjectForm from './project-form';
import ProjectRecordHeader from './project-record-header';

type Props = {
    project: Project;
};

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

export default function ProjectsEdit({ project }: Props) {
    const estimates: Estimate[] = project.estimates ?? [];

    return (
        <>
            <Head title={project.name} />

            <div>
                <div className="mb-4">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={projectsIndex()}>
                            <ArrowLeft className="mr-1 h-4 w-4" />
                            Back to projects
                        </Link>
                    </Button>
                </div>

                <ProjectRecordHeader project={project} />

                <div className="mt-6 grid gap-10 lg:grid-cols-[2fr_3fr]">
                    <div>
                        <h2 className="mb-4 text-lg font-semibold">Details</h2>
                        <ProjectForm
                            project={project}
                            submitLabel="Save changes"
                        />
                    </div>

                    <div>
                        <h2 className="mb-4 text-lg font-semibold">
                            Estimates
                        </h2>

                        {estimates.length === 0 ? (
                            <div className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                                No estimates yet. Click{' '}
                                <span className="font-medium text-foreground">
                                    New Estimate
                                </span>{' '}
                                above to upload a floor plan PDF.
                            </div>
                        ) : (
                            <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left">
                                            <th className="px-4 py-3 font-medium">
                                                Title
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Status
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Quote
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Updated
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {estimates.map((estimate) => (
                                            <tr
                                                key={estimate.id}
                                                className="min-h-14 cursor-pointer border-b transition-colors last:border-0 hover:bg-muted/50"
                                                onClick={() =>
                                                    router.get(
                                                        estimatesEdit(
                                                            estimate.id,
                                                        ).url,
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
                                                <td className="px-4 py-4">
                                                    {statusBadge(
                                                        estimate.status,
                                                    )}
                                                </td>
                                                <td className="px-4 py-4">
                                                    {estimate.quote_status ===
                                                        'sent' && (
                                                        <Badge variant="outline">
                                                            Sent
                                                        </Badge>
                                                    )}
                                                </td>
                                                <td className="px-4 py-4 whitespace-nowrap text-muted-foreground">
                                                    {formatDate(
                                                        estimate.updated_at,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

ProjectsEdit.layout = {
    breadcrumbs: [{ title: 'Projects', href: projectsIndex() }],
};
