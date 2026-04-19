import { Head } from '@inertiajs/react';
import { Circle } from 'lucide-react';
import QuoteLayout from '@/layouts/quote-layout';

type Step = {
    key: string;
    label: string;
    state: 'coming_soon' | 'pending' | 'complete';
};

type Props = {
    estimate: {
        title: string;
    };
    customer: {
        first_name: string;
    };
    account_name: string;
    steps: Step[];
};

export default function QuoteApprove({
    estimate,
    customer,
    account_name,
    steps,
}: Props) {
    return (
        <QuoteLayout accountName={account_name}>
            <Head title={`Approve — ${estimate.title}`} />

            <div className="space-y-8">
                <header className="space-y-2">
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Approve your quote
                    </h1>
                    <p className="text-muted-foreground">
                        Hi {customer.first_name} — you're ready to authorize
                        work on{' '}
                        <span className="font-medium">{estimate.title}</span>.
                        We'll walk you through a few short steps. You can leave
                        and return anytime using the link from your email.
                    </p>
                </header>

                <section>
                    <h2 className="mb-4 text-lg font-semibold">
                        What happens next
                    </h2>
                    <ol className="space-y-3 rounded-xl border bg-card p-4">
                        {steps.map((step, index) => (
                            <li
                                key={step.key}
                                className="flex items-center gap-3"
                            >
                                <span className="flex size-7 shrink-0 items-center justify-center rounded-full border text-sm font-medium text-muted-foreground">
                                    {index + 1}
                                </span>
                                <span className="flex-1 text-sm">
                                    {step.label}
                                </span>
                                <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <Circle className="size-3" />
                                    Coming soon
                                </span>
                            </li>
                        ))}
                    </ol>
                </section>

                <p className="text-sm text-muted-foreground">
                    We're still building out this flow. Your contractor will be
                    in touch directly in the meantime.
                </p>
            </div>
        </QuoteLayout>
    );
}
