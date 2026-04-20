import { Head, Link, usePage } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, Check } from 'lucide-react';
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

type QuoteItem = {
    id: number;
    kind: 'material' | 'labor';
    label: string;
    category: string;
    quantity: string;
    unit: string;
    unit_price: string;
    notes: string | null;
    line_total: string;
};

type Quote = {
    material_percent: number;
    labor_percent: number;
    items: QuoteItem[];
    material_subtotal: string;
    labor_subtotal: string;
    grand_total: string;
    material_deposit: string;
    labor_deposit: string;
    deposit_total: string;
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
    quote: Quote;
    quote_hash: string;
    is_locked: boolean;
    contract: {
        signed: Signed | null;
    };
    steps: Step[];
};

type FlashProps = {
    flash?: {
        quote_changed?: boolean;
        status?: string | null;
    };
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

function formatMoney(value: string): string {
    return Number(value).toLocaleString('en-US', {
        style: 'currency',
        currency: 'USD',
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

function QuoteBreakdown({ quote }: { quote: Quote }) {
    const materialItems = quote.items.filter((i) => i.kind === 'material');
    const laborItems = quote.items.filter((i) => i.kind === 'labor');

    return (
        <section className="rounded-xl border bg-card">
            <header className="border-b px-5 py-3">
                <h2 className="text-base font-semibold">Quote breakdown</h2>
            </header>
            <div className="divide-y">
                {materialItems.length > 0 && (
                    <ItemGroup label="Materials" items={materialItems} />
                )}
                {laborItems.length > 0 && (
                    <ItemGroup label="Labor" items={laborItems} />
                )}
                <div className="space-y-1 px-5 py-4 text-sm">
                    <div className="grid grid-cols-[1fr_auto] gap-3">
                        <span className="text-muted-foreground">
                            Materials subtotal
                        </span>
                        <span className="tabular-nums">
                            {formatMoney(quote.material_subtotal)}
                        </span>
                    </div>
                    <div className="grid grid-cols-[1fr_auto] gap-3">
                        <span className="text-muted-foreground">
                            Labor subtotal
                        </span>
                        <span className="tabular-nums">
                            {formatMoney(quote.labor_subtotal)}
                        </span>
                    </div>
                    <div className="grid grid-cols-[1fr_auto] gap-3 border-t pt-2 text-base font-semibold">
                        <span>Total</span>
                        <span className="tabular-nums">
                            {formatMoney(quote.grand_total)}
                        </span>
                    </div>
                </div>
                <div className="space-y-1 bg-muted/30 px-5 py-4 text-sm">
                    <h3 className="mb-1 text-sm font-semibold">
                        Deposit due today
                    </h3>
                    <div className="grid grid-cols-[1fr_auto] gap-3">
                        <span className="text-muted-foreground">
                            Materials ({quote.material_percent}%)
                        </span>
                        <span className="tabular-nums">
                            {formatMoney(quote.material_deposit)}
                        </span>
                    </div>
                    <div className="grid grid-cols-[1fr_auto] gap-3">
                        <span className="text-muted-foreground">
                            Labor ({quote.labor_percent}%)
                        </span>
                        <span className="tabular-nums">
                            {formatMoney(quote.labor_deposit)}
                        </span>
                    </div>
                    <div className="grid grid-cols-[1fr_auto] gap-3 border-t pt-2 text-base font-semibold">
                        <span>Deposit total</span>
                        <span className="tabular-nums">
                            {formatMoney(quote.deposit_total)}
                        </span>
                    </div>
                </div>
            </div>
        </section>
    );
}

function ItemGroup({ label, items }: { label: string; items: QuoteItem[] }) {
    return (
        <div>
            <div className="bg-muted/30 px-5 py-1.5 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                {label}
            </div>
            <div className="divide-y">
                {items.map((item) => (
                    <div key={item.id} className="space-y-0.5 px-5 py-3">
                        <div className="grid grid-cols-[1fr_auto_auto_auto] items-baseline gap-3 text-sm">
                            <span>{item.label}</span>
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {Number(item.quantity).toLocaleString('en-US', {
                                    minimumFractionDigits: Number.isInteger(
                                        Number(item.quantity),
                                    )
                                        ? 0
                                        : 2,
                                    maximumFractionDigits: 2,
                                })}{' '}
                                {item.unit}
                            </span>
                            <span className="text-xs text-muted-foreground tabular-nums">
                                @ {formatMoney(item.unit_price)}
                            </span>
                            <span className="w-24 text-right tabular-nums">
                                {formatMoney(item.line_total)}
                            </span>
                        </div>
                        {item.notes && (
                            <p className="text-xs text-muted-foreground">
                                {item.notes}
                            </p>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function QuoteApprove({
    estimate,
    customer,
    account_name,
    approval_token,
    quote,
    contract,
    steps,
}: Props) {
    const page = usePage<FlashProps>();
    const quoteChanged = Boolean(page.props.flash?.quote_changed);
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
                                Hi {customer.first_name} — here's the full
                                breakdown for{' '}
                                <span className="font-medium">
                                    {estimate.title}
                                </span>
                                . Review it below, then sign the agreement to
                                accept. You can come back to this link anytime.
                            </>
                        )}
                    </p>
                </header>

                {quoteChanged && (
                    <section className="flex items-start gap-3 rounded-xl border border-amber-400/60 bg-amber-50 p-4 dark:bg-amber-950/40">
                        <AlertTriangle className="size-5 shrink-0 text-amber-600" />
                        <div className="text-sm text-amber-900 dark:text-amber-100">
                            <span className="font-semibold">
                                Your quote was updated
                            </span>{' '}
                            since you last opened it. Review the current figures
                            below before signing.
                        </div>
                    </section>
                )}

                <QuoteBreakdown quote={quote} />

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
