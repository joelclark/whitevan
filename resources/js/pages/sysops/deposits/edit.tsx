import { Transition } from '@headlessui/react';
import { Form, Head, setLayoutProps } from '@inertiajs/react';
import DepositController from '@/actions/App/Http/Controllers/Sysops/DepositController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard as sysopsDashboard } from '@/routes/sysops';
import { edit as sysopsDepositsEdit } from '@/routes/sysops/deposits';

type Props = {
    defaults: {
        material_deposit_percent: number;
        labor_deposit_percent: number;
        updated_at: string | null;
    };
};

export default function SysopsDepositsEdit({ defaults }: Props) {
    setLayoutProps({
        title: 'Deposit Settings',
        description:
            'System-wide deposit percentages. Each account admin can override either side for their own account.',
    });

    return (
        <>
            <Head title="Deposit Settings" />

            <div className="max-w-2xl space-y-6">
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
                                        Default materials deposit %
                                    </Label>
                                    <Input
                                        id="material_deposit_percent"
                                        name="material_deposit_percent"
                                        type="number"
                                        min={0}
                                        max={100}
                                        step={1}
                                        defaultValue={
                                            defaults.material_deposit_percent
                                        }
                                        required
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Applies to material line items (physical
                                        goods).
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
                                        Default labor deposit %
                                    </Label>
                                    <Input
                                        id="labor_deposit_percent"
                                        name="labor_deposit_percent"
                                        type="number"
                                        min={0}
                                        max={100}
                                        step={1}
                                        defaultValue={
                                            defaults.labor_deposit_percent
                                        }
                                        required
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Applies to labor line items (demo, prep,
                                        install, services).
                                    </p>
                                    <InputError
                                        message={errors.labor_deposit_percent}
                                    />
                                </div>
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    className="h-12 px-5 text-base"
                                >
                                    Save changes
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
            </div>
        </>
    );
}

SysopsDepositsEdit.layout = {
    breadcrumbs: [
        { title: 'Sysops', href: sysopsDashboard().url },
        { title: 'Deposit Settings', href: sysopsDepositsEdit().url },
    ],
};
