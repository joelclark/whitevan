import { ChevronDown, Mail, MoreHorizontal, Phone } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Customer } from '@/types';
import DeleteCustomerDialog from './delete-customer-dialog';

type Props = {
    customer: Customer;
};

export default function CustomerRecordHeader({ customer }: Props) {
    const fullName = `${customer.first_name} ${customer.last_name}`.trim();

    return (
        <div className="flex flex-col gap-4 border-b pb-6 md:flex-row md:items-start md:justify-between">
            <div className="space-y-2">
                <h1 className="text-3xl font-semibold tracking-tight">
                    {fullName}
                </h1>
                {customer.company && (
                    <p className="text-muted-foreground">{customer.company}</p>
                )}
                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
                    {customer.phone && (
                        <span className="inline-flex items-center gap-1.5">
                            <Phone className="h-4 w-4" />
                            <a
                                href={`tel:${customer.phone}`}
                                className="hover:text-foreground"
                            >
                                {customer.phone}
                            </a>
                        </span>
                    )}
                    {customer.email && (
                        <span className="inline-flex items-center gap-1.5">
                            <Mail className="h-4 w-4" />
                            <a
                                href={`mailto:${customer.email}`}
                                className="hover:text-foreground"
                            >
                                {customer.email}
                            </a>
                        </span>
                    )}
                </div>
            </div>

            <div className="flex flex-shrink-0 items-center gap-2">
                <Button
                    variant="default"
                    disabled
                    title="Coming soon"
                    type="button"
                >
                    New Estimate
                    <ChevronDown className="ml-1 h-4 w-4" />
                </Button>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" size="icon" type="button">
                            <MoreHorizontal className="h-4 w-4" />
                            <span className="sr-only">More actions</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DeleteCustomerDialog
                            customer={customer}
                            trigger={
                                <DropdownMenuItem
                                    onSelect={(e) => e.preventDefault()}
                                    className="text-destructive focus:text-destructive"
                                >
                                    Delete customer
                                </DropdownMenuItem>
                            }
                        />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    );
}
