import { Transition } from '@headlessui/react';
import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import ContractTemplateController from '@/actions/App/Http/Controllers/Sysops/ContractTemplateController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { dashboard as sysopsDashboard } from '@/routes/sysops';
import { index as sysopsContractTemplatesIndex } from '@/routes/sysops/contract-templates';

type Template = {
    id: number;
    kind: string;
    label: string;
    body: string;
    preview_html: string;
    updated_at: string | null;
};

type Props = {
    template: Template;
};

export default function SysopsContractTemplateEdit({ template }: Props) {
    setLayoutProps({
        title: template.label,
        description:
            'Default contract every account inherits unless overridden.',
    });

    return (
        <>
            <Head title={`${template.label} — Contract templates`} />

            <div>
                <div className="mb-4">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={sysopsContractTemplatesIndex()}>
                            <ArrowLeft className="mr-1 h-4 w-4" />
                            Back to contract templates
                        </Link>
                    </Button>
                </div>

                <div className="max-w-3xl space-y-6">
                    <Form
                        {...ContractTemplateController.update.form(template.id)}
                        options={{ preserveScroll: true }}
                        className="space-y-6"
                    >
                        {({ processing, recentlySuccessful, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="body" className="text-base">
                                        Body
                                    </Label>
                                    <p className="text-sm text-muted-foreground">
                                        Markdown is supported — headings, lists,
                                        emphasis, and links render through to
                                        the customer-facing page.
                                    </p>
                                    <Textarea
                                        id="body"
                                        name="body"
                                        defaultValue={template.body}
                                        rows={22}
                                        maxLength={50000}
                                        className="font-mono text-sm"
                                        required
                                    />
                                    <InputError message={errors.body} />
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

                    <section className="rounded-xl border bg-muted/20 p-5">
                        <h2 className="mb-3 text-sm font-semibold">Preview</h2>
                        <div
                            className="prose prose-sm dark:prose-invert max-w-none"
                            dangerouslySetInnerHTML={{
                                __html: template.preview_html,
                            }}
                        />
                    </section>
                </div>
            </div>
        </>
    );
}

SysopsContractTemplateEdit.layout = {
    breadcrumbs: [
        { title: 'Sysops', href: sysopsDashboard().url },
        {
            title: 'Contract templates',
            href: sysopsContractTemplatesIndex().url,
        },
    ],
};
