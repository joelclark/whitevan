import { Transition } from '@headlessui/react';
import { Form, Head } from '@inertiajs/react';
import { ChevronDown, ChevronUp, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ContractController from '@/actions/App/Http/Controllers/Admin/ContractController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { edit as contractEdit } from '@/routes/admin/contract';

type Props = {
    override: { body: string; updated_at: string | null } | null;
    default: { body: string; preview_html: string };
};

export default function AdminContractEdit({
    override,
    default: defaultTemplate,
}: Props) {
    const [showPreview, setShowPreview] = useState(false);
    const [confirmRevert, setConfirmRevert] = useState(false);

    const usingCustom = override !== null;

    return (
        <>
            <Head title="Contract" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Contract"
                    description="The agreement your customers sign when approving a quote."
                />

                <div className="flex items-center gap-3">
                    <span className="text-sm text-muted-foreground">
                        Current status:
                    </span>
                    <span
                        className={
                            usingCustom
                                ? 'inline-flex items-center rounded-full border border-primary/30 bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary'
                                : 'inline-flex items-center rounded-full border bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground'
                        }
                    >
                        {usingCustom
                            ? 'Using custom contract'
                            : 'Using default contract'}
                    </span>
                </div>

                <section className="rounded-xl border bg-muted/20">
                    <button
                        type="button"
                        onClick={() => setShowPreview((v) => !v)}
                        className="flex w-full items-center justify-between gap-2 p-4 text-left text-sm font-semibold"
                    >
                        <span>Preview the default contract</span>
                        {showPreview ? (
                            <ChevronUp className="h-4 w-4 text-muted-foreground" />
                        ) : (
                            <ChevronDown className="h-4 w-4 text-muted-foreground" />
                        )}
                    </button>
                    {showPreview && (
                        <div
                            className="prose prose-sm dark:prose-invert max-w-none border-t p-5"
                            dangerouslySetInnerHTML={{
                                __html: defaultTemplate.preview_html,
                            }}
                        />
                    )}
                </section>

                <Form
                    {...ContractController.update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, recentlySuccessful, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="body" className="text-base">
                                    Your contract
                                </Label>
                                <p className="text-sm text-muted-foreground">
                                    Markdown is supported — headings, lists,
                                    emphasis, and links render through to the
                                    customer-facing page. Leave empty and use
                                    the default by reverting below.
                                </p>
                                <Textarea
                                    id="body"
                                    name="body"
                                    defaultValue={override?.body ?? ''}
                                    rows={18}
                                    maxLength={50000}
                                    className="font-mono text-sm"
                                    placeholder={
                                        usingCustom
                                            ? ''
                                            : 'Write your custom contract here to override the default…'
                                    }
                                    required
                                />
                                <InputError message={errors.body} />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    className="h-10 px-5"
                                >
                                    {usingCustom
                                        ? 'Save changes'
                                        : 'Save custom contract'}
                                </Button>
                                <Transition
                                    show={recentlySuccessful}
                                    enter="transition ease-in-out"
                                    enterFrom="opacity-0"
                                    leave="transition ease-in-out"
                                    leaveTo="opacity-0"
                                >
                                    <p className="text-sm text-muted-foreground">
                                        Saved
                                    </p>
                                </Transition>
                            </div>
                        </>
                    )}
                </Form>

                {usingCustom && (
                    <section className="rounded-xl border border-destructive/30 bg-destructive/5 p-5">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p className="text-sm font-medium">
                                    Revert to the default contract
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Remove your custom text and use the system
                                    default going forward.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                                onClick={() => setConfirmRevert(true)}
                            >
                                <Trash2 className="mr-1 h-4 w-4" />
                                Revert
                            </Button>
                        </div>
                    </section>
                )}

                <Dialog open={confirmRevert} onOpenChange={setConfirmRevert}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                Revert to the default contract?
                            </DialogTitle>
                            <DialogDescription>
                                This removes your custom contract text.
                                Already-signed estimates keep the text the
                                customer originally agreed to.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setConfirmRevert(false)}
                            >
                                Cancel
                            </Button>
                            <Form
                                {...ContractController.destroy.form()}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setConfirmRevert(false)}
                            >
                                <Button type="submit" variant="destructive">
                                    Revert
                                </Button>
                            </Form>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

AdminContractEdit.layout = {
    breadcrumbs: [{ title: 'Contract', href: contractEdit() }],
};
