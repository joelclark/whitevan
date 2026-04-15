import { Form } from '@inertiajs/react';
import { FileUp, Loader2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useRef, useState } from 'react';
import EstimateController from '@/actions/App/Http/Controllers/EstimateController';
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
import type { Customer } from '@/types';

type Props = {
    customer: Customer;
    trigger: ReactNode;
};

function formatBytes(bytes: number): string {
    if (bytes >= 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    if (bytes >= 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${bytes} B`;
}

export default function UploadEstimateDialog({ customer, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const [file, setFile] = useState<File | null>(null);
    const [isDragging, setIsDragging] = useState(false);
    const inputRef = useRef<HTMLInputElement | null>(null);

    const reset = () => {
        setFile(null);
        setIsDragging(false);

        if (inputRef.current) {
            inputRef.current.value = '';
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                setOpen(nextOpen);

                if (!nextOpen) {
                    reset();
                }
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogTitle>New estimate</DialogTitle>
                <DialogDescription>
                    Upload the floor plan PDF you exported from the measuring
                    tool. We&apos;ll read it and pull out rooms automatically.
                </DialogDescription>

                <Form
                    {...EstimateController.store.form(customer.id)}
                    onSuccess={() => {
                        setOpen(false);
                        reset();
                    }}
                >
                    {({ processing, errors }) => (
                        <>
                            <div
                                className={`mt-2 flex min-h-48 flex-col items-center justify-center rounded-xl border-2 border-dashed p-6 text-center transition-colors ${
                                    isDragging
                                        ? 'border-primary bg-primary/5'
                                        : 'border-muted-foreground/30'
                                }`}
                                onDragOver={(e) => {
                                    e.preventDefault();
                                    setIsDragging(true);
                                }}
                                onDragLeave={() => setIsDragging(false)}
                                onDrop={(e) => {
                                    e.preventDefault();
                                    setIsDragging(false);
                                    const dropped = e.dataTransfer.files?.[0];

                                    if (
                                        dropped &&
                                        dropped.type === 'application/pdf'
                                    ) {
                                        setFile(dropped);

                                        if (inputRef.current) {
                                            const dt = new DataTransfer();
                                            dt.items.add(dropped);
                                            inputRef.current.files = dt.files;
                                        }
                                    }
                                }}
                            >
                                <FileUp className="mb-3 h-10 w-10 text-muted-foreground" />

                                {file ? (
                                    <div className="space-y-2">
                                        <p className="text-base font-medium">
                                            {file.name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {formatBytes(file.size)}
                                        </p>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={reset}
                                        >
                                            Pick a different file
                                        </Button>
                                    </div>
                                ) : (
                                    <>
                                        <p className="mb-3 text-base">
                                            Drop a PDF here, or
                                        </p>
                                        <Button
                                            type="button"
                                            variant="default"
                                            className="h-12 px-5 text-base"
                                            onClick={() =>
                                                inputRef.current?.click()
                                            }
                                        >
                                            Choose PDF
                                        </Button>
                                        <p className="mt-3 text-xs text-muted-foreground">
                                            Up to 25 MB.
                                        </p>
                                    </>
                                )}

                                <input
                                    ref={inputRef}
                                    type="file"
                                    name="pdf"
                                    accept="application/pdf"
                                    className="hidden"
                                    onChange={(e) =>
                                        setFile(e.target.files?.[0] ?? null)
                                    }
                                />
                            </div>

                            <InputError className="mt-2" message={errors.pdf} />

                            <DialogFooter className="mt-4 gap-2">
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
                                    disabled={processing || !file}
                                    className="h-12 px-5 text-base"
                                >
                                    {processing && (
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    )}
                                    {processing
                                        ? 'Uploading…'
                                        : 'Start estimate'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
