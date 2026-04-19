import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import QuoteLayout from '@/layouts/quote-layout';
import { show as signShow } from '@/routes/approve/sign';

type StepState = 'coming_soon' | 'pending' | 'complete';

type Step = {
    key: string;
    label: string;
    state: StepState;
};

type Signed = {
    name: string;
    signed_at: string;
};

type Props = {
    estimate: {
        title: string;
    };
    customer: {
        first_name: string;
    };
    account_name: string;
    approval_token: string;
    contract: {
        signed: Signed | null;
    };
    steps: Step[];
};

function formatDate(value: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString(undefined, {
        dateStyle: 'long',
        timeStyle: 'short',
    });
}

function StepRow({
    step,
    index,
    action,
}: {
    step: Step;
    index: number;
    action: React.ReactNode;
}) {
    return (
        <li className="flex min-h-12 items-center gap-4 py-2">
            <span className="flex size-6 shrink-0 items-center justify-center rounded-full border text-xs font-medium text-muted-foreground">
                {index + 1}
            </span>
            <div className="min-w-0 flex-1">
                <p className="text-sm font-medium">{step.label}</p>
            </div>
            <div className="shrink-0">{action}</div>
        </li>
    );
}

export default function QuoteApprove({
    estimate,
    customer,
    account_name,
    approval_token,
    contract,
    steps,
}: Props) {
    const signed = contract.signed;
    const signUrl = signShow(approval_token).url;

    return (
        <QuoteLayout accountName={account_name}>
            <Head title={`Approve — ${estimate.title}`} />

            <div className="space-y-8">
                <header className="space-y-2">
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Approve your quote
                    </h1>
                    <p className="text-muted-foreground">
                        {signed ? (
                            <>
                                Hi {customer.first_name} — thanks for signing
                                the agreement for{' '}
                                <span className="font-medium">
                                    {estimate.title}
                                </span>
                                . Your contractor will be in touch about the
                                remaining steps below.
                            </>
                        ) : (
                            <>
                                Hi {customer.first_name} — you're ready to
                                authorize work on{' '}
                                <span className="font-medium">
                                    {estimate.title}
                                </span>
                                . We'll walk you through a few short steps. You
                                can leave and return anytime using the link from
                                your email.
                            </>
                        )}
                    </p>
                </header>

                <section>
                    <h2 className="mb-3 text-lg font-semibold">
                        What happens next
                    </h2>
                    <ol className="divide-y rounded-xl border bg-card px-5">
                        {steps.map((step, index) => (
                            <StepRow
                                key={step.key}
                                step={step}
                                index={index}
                                action={
                                    step.key === 'sign' &&
                                    step.state === 'pending' ? (
                                        <Button size="sm" asChild>
                                            <Link href={signUrl}>
                                                View &amp; sign
                                                <ArrowRight className="ml-1 size-4" />
                                            </Link>
                                        </Button>
                                    ) : step.key === 'sign' &&
                                      step.state === 'complete' &&
                                      signed ? (
                                        <Link
                                            href={signUrl}
                                            className="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700 underline-offset-2 hover:underline dark:text-emerald-400"
                                        >
                                            <Check className="size-3.5" />
                                            Signed{' '}
                                            {formatDate(signed.signed_at)}
                                        </Link>
                                    ) : (
                                        <span className="text-xs text-muted-foreground">
                                            Coming soon
                                        </span>
                                    )
                                }
                            />
                        ))}
                    </ol>
                </section>
            </div>
        </QuoteLayout>
    );
}
