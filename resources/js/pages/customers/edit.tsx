import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, FolderOpen } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { index as customersIndex } from '@/routes/customers';
import { edit as projectsEdit } from '@/routes/projects';
import type { Customer } from '@/types';
import CustomerForm from './customer-form';
import CustomerRecordHeader from './customer-record-header';

type Props = {
    customer: Customer;
};

export default function CustomersEdit({ customer }: Props) {
    const fullName = `${customer.first_name} ${customer.last_name}`.trim();
    const projects = customer.projects ?? [];

    return (
        <>
            <Head title={fullName} />

            <div>
                <div className="mb-4">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={customersIndex()}>
                            <ArrowLeft className="mr-1 h-4 w-4" />
                            Back to customers
                        </Link>
                    </Button>
                </div>

                <CustomerRecordHeader customer={customer} />

                <div className="mt-6 grid gap-10 lg:grid-cols-[2fr_3fr]">
                    <div>
                        <h2 className="mb-4 text-lg font-semibold">Details</h2>
                        <CustomerForm
                            mode="edit"
                            customer={customer}
                            submitLabel="Save changes"
                        />
                    </div>

                    <div>
                        <h2 className="mb-4 text-lg font-semibold">Projects</h2>

                        {projects.length === 0 ? (
                            <div className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                                No projects yet. Click{' '}
                                <span className="font-medium text-foreground">
                                    New Project
                                </span>{' '}
                                above to start one.
                            </div>
                        ) : (
                            <ul className="divide-y rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                {projects.map((project) => (
                                    <li
                                        key={project.id}
                                        className="flex cursor-pointer items-center justify-between gap-4 px-4 py-4 transition-colors hover:bg-muted/50"
                                        onClick={() =>
                                            router.get(
                                                projectsEdit(project.id).url,
                                            )
                                        }
                                    >
                                        <div className="min-w-0 flex-1">
                                            <Link
                                                href={projectsEdit(project.id)}
                                                onClick={(e) =>
                                                    e.stopPropagation()
                                                }
                                                className="inline-flex items-center gap-2 font-medium hover:underline"
                                            >
                                                <FolderOpen className="h-4 w-4 text-muted-foreground" />
                                                {project.name}
                                            </Link>
                                        </div>
                                        <span className="text-sm text-muted-foreground">
                                            {project.estimates_count ?? 0}{' '}
                                            estimate
                                            {project.estimates_count === 1
                                                ? ''
                                                : 's'}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

CustomersEdit.layout = {
    breadcrumbs: [{ title: 'Customers', href: customersIndex() }],
};
