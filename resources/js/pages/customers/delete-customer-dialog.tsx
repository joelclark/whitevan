import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { Customer } from '@/types';

type Props = {
    customer: Customer;
    trigger: ReactNode;
};

export default function DeleteCustomerDialog({ customer, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const fullName = `${customer.first_name} ${customer.last_name}`.trim();

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete {fullName}?</DialogTitle>
                <DialogDescription>
                    This customer will be moved to the archive. Their data is
                    retained and can be restored by an administrator.
                </DialogDescription>

                <Form
                    {...CustomerController.destroy.form(customer.id)}
                    options={{ preserveScroll: false }}
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary" type="button">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button
                                variant="destructive"
                                type="submit"
                                disabled={processing}
                            >
                                Delete customer
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
