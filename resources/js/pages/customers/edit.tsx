import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { index as customersIndex } from '@/routes/customers';
import type { Customer } from '@/types';
import CustomerForm from './customer-form';
import CustomerRecordHeader from './customer-record-header';

type Props = {
    customer: Customer;
};

export default function CustomersEdit({ customer }: Props) {
    const fullName = `${customer.first_name} ${customer.last_name}`.trim();

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

                <div className="mt-6 max-w-3xl">
                    <h2 className="mb-4 text-lg font-semibold">Details</h2>
                    <CustomerForm
                        mode="edit"
                        customer={customer}
                        submitLabel="Save changes"
                    />
                </div>
            </div>
        </>
    );
}

CustomersEdit.layout = {
    breadcrumbs: [{ title: 'Customers', href: customersIndex() }],
};
