import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { index as customersIndex } from '@/routes/customers';
import CustomerForm from './customer-form';

export default function CustomersCreate() {
    return (
        <>
            <Head title="New customer" />

            <div>
                <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="New customer"
                        description="Add a new customer to your account."
                    />
                    <Button variant="ghost" asChild>
                        <Link href={customersIndex()}>
                            <ArrowLeft className="mr-1 h-4 w-4" />
                            Back to customers
                        </Link>
                    </Button>
                </div>

                <div className="max-w-3xl">
                    <CustomerForm mode="create" submitLabel="Create customer" />
                </div>
            </div>
        </>
    );
}

CustomersCreate.layout = {
    breadcrumbs: [
        { title: 'Customers', href: customersIndex() },
        { title: 'New', href: { url: '/customers/create', method: 'get' } },
    ],
};
