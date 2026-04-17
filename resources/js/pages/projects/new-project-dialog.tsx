import { Form } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Customer } from '@/types';

type Props = {
    customer: Customer;
    trigger: ReactNode;
};

export default function NewProjectDialog({ customer, trigger }: Props) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                setOpen(nextOpen);
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogTitle>New project</DialogTitle>
                <DialogDescription>
                    Give the job a name. You can add a job-site address and
                    estimates after it&apos;s created.
                </DialogDescription>

                <Form
                    {...ProjectController.store.form(customer.id)}
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2 py-2">
                                <Label htmlFor="name">
                                    Project name{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    autoFocus
                                    maxLength={255}
                                    placeholder="e.g. Main floor, Kitchen remodel"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <DialogFooter className="mt-4 gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && (
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    )}
                                    {processing
                                        ? 'Creating…'
                                        : 'Create project'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
