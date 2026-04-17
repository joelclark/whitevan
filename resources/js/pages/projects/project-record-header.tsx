import { Link } from '@inertiajs/react';
import { FilePlus2, MapPin, MoreHorizontal, User } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UploadEstimateDialog from '@/pages/estimates/upload-estimate-dialog';
import { edit as customersEdit } from '@/routes/customers';
import type { Project } from '@/types';
import DeleteProjectDialog from './delete-project-dialog';

type Props = {
    project: Project;
};

function formatSiteAddress(project: Project): string | null {
    const parts: string[] = [];

    if (project.site_address_line_1) {
        parts.push(project.site_address_line_1);
    }

    if (project.site_address_line_2) {
        parts.push(project.site_address_line_2);
    }

    const cityStateZip = [
        project.site_city,
        project.site_state,
        project.site_zip,
    ]
        .filter((p): p is string => p !== null && p !== '')
        .join(' ');

    if (cityStateZip) {
        parts.push(cityStateZip);
    }

    return parts.length > 0 ? parts.join(', ') : null;
}

export default function ProjectRecordHeader({ project }: Props) {
    const customer = project.customer;
    const customerName = customer
        ? `${customer.first_name} ${customer.last_name}`.trim()
        : null;
    const siteAddress = formatSiteAddress(project);

    return (
        <div className="flex flex-col gap-4 border-b pb-6 md:flex-row md:items-start md:justify-between">
            <div className="space-y-2">
                <h1 className="text-3xl font-semibold tracking-tight">
                    {project.name}
                </h1>
                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
                    {customer && (
                        <Link
                            href={customersEdit(customer.id)}
                            className="inline-flex items-center gap-1.5 hover:text-foreground"
                        >
                            <User className="h-4 w-4" />
                            {customerName || customer.company || 'Customer'}
                        </Link>
                    )}
                    {siteAddress && (
                        <span className="inline-flex items-center gap-1.5">
                            <MapPin className="h-4 w-4" />
                            {siteAddress}
                        </span>
                    )}
                </div>
            </div>

            <div className="flex flex-shrink-0 items-center gap-2">
                <UploadEstimateDialog
                    project={project}
                    trigger={
                        <Button
                            variant="default"
                            type="button"
                            className="h-12 px-5 text-base"
                        >
                            <FilePlus2 className="mr-2 h-4 w-4" />
                            New Estimate
                        </Button>
                    }
                />
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="outline"
                            size="icon"
                            type="button"
                            className="h-12 w-12"
                        >
                            <MoreHorizontal className="h-5 w-5" />
                            <span className="sr-only">More actions</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DeleteProjectDialog
                            project={project}
                            trigger={
                                <DropdownMenuItem
                                    onSelect={(e) => e.preventDefault()}
                                    className="text-destructive focus:text-destructive"
                                >
                                    Delete project
                                </DropdownMenuItem>
                            }
                        />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    );
}
