import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ChevronRight, FileText } from 'lucide-react';
import { dashboard as sysopsDashboard } from '@/routes/sysops';
import {
    edit as sysopsContractTemplateEdit,
    index as sysopsContractTemplatesIndex,
} from '@/routes/sysops/contract-templates';

type Template = {
    id: number;
    kind: string;
    label: string;
    updated_at: string | null;
};

type Props = {
    templates: Template[];
};

export default function SysopsContractTemplatesIndex({ templates }: Props) {
    setLayoutProps({
        title: 'Contract templates',
        description:
            'Default contract every account inherits unless an account admin overrides it.',
    });

    return (
        <>
            <Head title="Contract templates" />

            <div className="mt-6 space-y-3">
                {templates.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                        No contract templates are configured yet.
                    </div>
                ) : (
                    templates.map((template) => (
                        <Link
                            key={template.id}
                            href={sysopsContractTemplateEdit(template.id)}
                            className="flex min-h-16 items-center justify-between gap-4 rounded-xl border bg-card p-5 transition-colors hover:bg-muted/40"
                        >
                            <div className="flex min-w-0 items-start gap-4">
                                <div className="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <FileText className="h-5 w-5" />
                                </div>
                                <div className="min-w-0">
                                    <p className="text-base font-semibold">
                                        {template.label}
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Kind: {template.kind}
                                    </p>
                                </div>
                            </div>
                            <ChevronRight className="h-5 w-5 flex-shrink-0 text-muted-foreground" />
                        </Link>
                    ))
                )}
            </div>
        </>
    );
}

SysopsContractTemplatesIndex.layout = {
    breadcrumbs: [
        { title: 'Sysops', href: sysopsDashboard().url },
        {
            title: 'Contract templates',
            href: sysopsContractTemplatesIndex().url,
        },
    ],
};
