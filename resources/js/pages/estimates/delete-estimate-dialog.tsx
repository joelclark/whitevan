import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import EstimateController from '@/actions/App/Http/Controllers/EstimateController';
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
import type { Estimate } from '@/types';

type Props = {
    estimate: Estimate;
    trigger: ReactNode;
};

export default function DeleteEstimateDialog({ estimate, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const displayTitle = estimate.title ?? estimate.pdf_original_filename;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete “{displayTitle}”?</DialogTitle>
                <DialogDescription>
                    This estimate and its uploaded PDF will be removed. Room
                    data and interview answers will be archived alongside it.
                </DialogDescription>

                <Form
                    {...EstimateController.destroy.form(estimate.id)}
                    options={{ preserveScroll: false }}
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button
                                    variant="secondary"
                                    type="button"
                                    className="h-12 px-5 text-base"
                                >
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button
                                variant="destructive"
                                type="submit"
                                disabled={processing}
                                className="h-12 px-5 text-base"
                            >
                                Delete estimate
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
