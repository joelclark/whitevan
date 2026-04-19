import { Transition } from '@headlessui/react';
import { Form, Head } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import DepositController from '@/actions/App/Http/Controllers/Admin/DepositController';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit as depositsEdit } from '@/routes/admin/deposits';

type Props = {
    override: {
        material_deposit_percent: number | null;
        labor_deposit_percent: number | null;
        updated_at: string | null;
    } | null;
    defaults: {
        material_deposit_percent: number;
        labor_deposit_percent: number;
    };
    resolved: {
        material_deposit_percent: number;
        labor_deposit_percent: number;
    };
};

export default function AdminDepositsEdit({
    override,
    defaults,
    resolved,
}: Props) {
    const [confirmRevert, setConfirmRevert] = useState(false);

    const usingCustom = override !== null;

    return (
        <>
            <Head title="Deposits" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Deposits"
                    description="The percentage of each quote owed upfront at signing. Split between materials and labor."
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
                            ? 'Using custom deposits'
                            : 'Using default deposits'}
                    </span>
                </div>

                <section className="rounded-xl border bg-muted/20 p-5">
                    <h2 className="mb-3 text-sm font-semibold">
                        System defaults
                    </h2>
                    <dl className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                        <dt className="text-muted-foreground">Materials</dt>
                        <dd className="font-medium">
                            {defaults.material_deposit_percent}%
                        </dd>
                        <dt className="text-muted-foreground">Labor</dt>
                        <dd className="font-medium">
                            {defaults.labor_deposit_percent}%
                        </dd>
                    </dl>
                    <p className="mt-3 text-xs text-muted-foreground">
                        Leave a field below blank to inherit the system default
                        for that side.
                    </p>
                </section>

                <Form
                    {...DepositController.update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, recentlySuccessful, errors }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor="material_deposit_percent"
                                        className="text-base"
                                    >
                                        Materials deposit %
                                    </Label>
                                    <Input
                                        id="material_deposit_percent"
                                        name="material_deposit_percent"
                                        type="number"
                                        min={0}
                                        max={100}
                                        step={1}
                                        defaultValue={
                                            override?.material_deposit_percent ??
                                            ''
                                        }
                                        placeholder={`Default: ${defaults.material_deposit_percent}`}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Applies to materials (physical goods).
                                        Currently resolved:{' '}
                                        <span className="font-medium text-foreground">
                                            {resolved.material_deposit_percent}%
                                        </span>
                                    </p>
                                    <InputError
                                        message={
                                            errors.material_deposit_percent
                                        }
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor="labor_deposit_percent"
                                        className="text-base"
                                    >
                                        Labor deposit %
                                    </Label>
                                    <Input
                                        id="labor_deposit_percent"
                                        name="labor_deposit_percent"
                                        type="number"
                                        min={0}
                                        max={100}
                                        step={1}
                                        defaultValue={
                                            override?.labor_deposit_percent ??
                                            ''
                                        }
                                        placeholder={`Default: ${defaults.labor_deposit_percent}`}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Applies to labor (install, demo, prep,
                                        services). Currently resolved:{' '}
                                        <span className="font-medium text-foreground">
                                            {resolved.labor_deposit_percent}%
                                        </span>
                                    </p>
                                    <InputError
                                        message={errors.labor_deposit_percent}
                                    />
                                </div>
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    className="h-10 px-5"
                                >
                                    {usingCustom
                                        ? 'Save changes'
                                        : 'Save custom deposits'}
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
                                    Revert to the default deposits
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Remove your custom percentages and use the
                                    system defaults going forward.
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
                                Revert to the default deposits?
                            </DialogTitle>
                            <DialogDescription>
                                This removes your custom percentages. Quotes
                                already accepted by customers are locked and
                                unaffected; new quotes will use the system
                                defaults.
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
                                {...DepositController.destroy.form()}
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

AdminDepositsEdit.layout = {
    breadcrumbs: [{ title: 'Deposits', href: depositsEdit() }],
};
