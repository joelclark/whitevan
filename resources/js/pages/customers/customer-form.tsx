import { Transition } from '@headlessui/react';
import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { Customer } from '@/types';

type Props = {
    mode: 'create' | 'edit';
    customer?: Customer;
    submitLabel?: string;
    footerRight?: ReactNode;
};

export default function CustomerForm({
    mode,
    customer,
    submitLabel = 'Save',
    footerRight,
}: Props) {
    const formProps =
        mode === 'create'
            ? CustomerController.store.form()
            : CustomerController.update.form(customer!.id);

    return (
        <Form
            {...formProps}
            options={{ preserveScroll: true }}
            className="space-y-6"
        >
            {({ processing, recentlySuccessful, errors }) => (
                <>
                    <div className="grid gap-6 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="first_name">
                                First name{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="first_name"
                                name="first_name"
                                defaultValue={customer?.first_name ?? ''}
                                required
                                autoComplete="given-name"
                                maxLength={255}
                            />
                            <InputError message={errors.first_name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="last_name">
                                Last name{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="last_name"
                                name="last_name"
                                defaultValue={customer?.last_name ?? ''}
                                required
                                autoComplete="family-name"
                                maxLength={255}
                            />
                            <InputError message={errors.last_name} />
                        </div>

                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="company">Company</Label>
                            <Input
                                id="company"
                                name="company"
                                defaultValue={customer?.company ?? ''}
                                autoComplete="organization"
                                maxLength={255}
                            />
                            <InputError message={errors.company} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                defaultValue={customer?.email ?? ''}
                                autoComplete="email"
                                maxLength={255}
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="phone">Phone</Label>
                            <Input
                                id="phone"
                                name="phone"
                                defaultValue={customer?.phone ?? ''}
                                autoComplete="tel"
                                maxLength={255}
                            />
                            <InputError message={errors.phone} />
                        </div>

                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="address_line_1">Address</Label>
                            <Input
                                id="address_line_1"
                                name="address_line_1"
                                defaultValue={customer?.address_line_1 ?? ''}
                                autoComplete="address-line1"
                                maxLength={255}
                                placeholder="Street address"
                            />
                            <InputError message={errors.address_line_1} />
                        </div>

                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="address_line_2" className="sr-only">
                                Address line 2
                            </Label>
                            <Input
                                id="address_line_2"
                                name="address_line_2"
                                defaultValue={customer?.address_line_2 ?? ''}
                                autoComplete="address-line2"
                                maxLength={255}
                                placeholder="Apartment, suite, etc."
                            />
                            <InputError message={errors.address_line_2} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="city">City</Label>
                            <Input
                                id="city"
                                name="city"
                                defaultValue={customer?.city ?? ''}
                                autoComplete="address-level2"
                                maxLength={255}
                            />
                            <InputError message={errors.city} />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="state">State</Label>
                                <Input
                                    id="state"
                                    name="state"
                                    defaultValue={customer?.state ?? ''}
                                    autoComplete="address-level1"
                                    maxLength={255}
                                />
                                <InputError message={errors.state} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="zip">ZIP</Label>
                                <Input
                                    id="zip"
                                    name="zip"
                                    defaultValue={customer?.zip ?? ''}
                                    autoComplete="postal-code"
                                    maxLength={255}
                                />
                                <InputError message={errors.zip} />
                            </div>
                        </div>

                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="notes">Notes</Label>
                            <Textarea
                                id="notes"
                                name="notes"
                                defaultValue={customer?.notes ?? ''}
                                maxLength={5000}
                                rows={5}
                            />
                            <InputError message={errors.notes} />
                        </div>
                    </div>

                    <div className="flex items-center justify-between gap-4">
                        <div className="flex items-center gap-4">
                            <Button disabled={processing}>{submitLabel}</Button>
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
                        {footerRight}
                    </div>
                </>
            )}
        </Form>
    );
}
