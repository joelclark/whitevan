import { Form } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
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

export default function ResubmitEstimateDialog({ estimate, trigger }: Props) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>Resubmit this PDF?</DialogTitle>
                <DialogDescription>
                    The AI agent will run again on the same PDF. The title,
                    total square footage, and room list will be replaced with
                    whatever comes back. Interview answers and line item prices
                    are kept.
                </DialogDescription>

                <Form
                    {...EstimateController.retry.form(estimate.id)}
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
                                type="submit"
                                disabled={processing}
                                className="h-12 px-5 text-base"
                            >
                                <RefreshCw
                                    className={`mr-2 h-4 w-4 ${
                                        processing ? 'animate-spin' : ''
                                    }`}
                                />
                                {processing ? 'Resubmitting…' : 'Resubmit PDF'}
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
