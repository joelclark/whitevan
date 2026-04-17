import { Transition } from '@headlessui/react';
import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { Project } from '@/types';

type Props = {
    project: Project;
    submitLabel?: string;
    footerRight?: ReactNode;
};

export default function ProjectForm({
    project,
    submitLabel = 'Save',
    footerRight,
}: Props) {
    return (
        <Form
            {...ProjectController.update.form(project.id)}
            options={{ preserveScroll: true }}
            className="space-y-6"
        >
            {({ processing, recentlySuccessful, errors }) => (
                <>
                    <div className="grid gap-6 md:grid-cols-2">
                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="name">
                                Project name{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="name"
                                name="name"
                                defaultValue={project.name}
                                required
                                maxLength={255}
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="site_address_line_1">
                                Job site address
                            </Label>
                            <Input
                                id="site_address_line_1"
                                name="site_address_line_1"
                                defaultValue={project.site_address_line_1 ?? ''}
                                autoComplete="address-line1"
                                maxLength={255}
                                placeholder="Street address (leave blank to use customer's address)"
                            />
                            <InputError message={errors.site_address_line_1} />
                        </div>

                        <div className="grid gap-2 md:col-span-2">
                            <Label
                                htmlFor="site_address_line_2"
                                className="sr-only"
                            >
                                Job site address line 2
                            </Label>
                            <Input
                                id="site_address_line_2"
                                name="site_address_line_2"
                                defaultValue={project.site_address_line_2 ?? ''}
                                autoComplete="address-line2"
                                maxLength={255}
                                placeholder="Apartment, suite, etc."
                            />
                            <InputError message={errors.site_address_line_2} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="site_city">City</Label>
                            <Input
                                id="site_city"
                                name="site_city"
                                defaultValue={project.site_city ?? ''}
                                autoComplete="address-level2"
                                maxLength={255}
                            />
                            <InputError message={errors.site_city} />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="site_state">State</Label>
                                <Input
                                    id="site_state"
                                    name="site_state"
                                    defaultValue={project.site_state ?? ''}
                                    autoComplete="address-level1"
                                    maxLength={255}
                                />
                                <InputError message={errors.site_state} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="site_zip">ZIP</Label>
                                <Input
                                    id="site_zip"
                                    name="site_zip"
                                    defaultValue={project.site_zip ?? ''}
                                    autoComplete="postal-code"
                                    maxLength={255}
                                />
                                <InputError message={errors.site_zip} />
                            </div>
                        </div>

                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="notes">Notes</Label>
                            <Textarea
                                id="notes"
                                name="notes"
                                defaultValue={project.notes ?? ''}
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
