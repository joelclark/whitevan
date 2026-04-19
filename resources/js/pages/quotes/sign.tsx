import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Check, CircleCheck } from 'lucide-react';
import ContractSigningController from '@/actions/App/Http/Controllers/ContractSigningController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import QuoteLayout from '@/layouts/quote-layout';
import { show as approvalShow } from '@/routes/approve';

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
    account_name: string;
    approval_token: string;
    quote: Quote;
    quote_hash: string;
    is_locked: boolean;
    contract: {
        body_html: string;
        signed: Signed | null;
    };
};

function formatMoney(value: string): string {
    return Number(value).toLocaleString('en-US', {
        style: 'currency',
        currency: 'USD',
    });
}

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

export default function QuoteSign({
    estimate,
    account_name,
    approval_token,
    quote,
    quote_hash,
    contract,
}: Props) {
    const signed = contract.signed;
    const backUrl = approvalShow(approval_token).url;

    return (
        <QuoteLayout accountName={account_name}>
            <Head title={`Sign agreement — ${estimate.title}`} />

            <div className="space-y-8">
                <div>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={backUrl}>
                            <ArrowLeft className="mr-1 size-4" />
                            Back to overview
                        </Link>
                    </Button>
                </div>

                <header className="space-y-2">
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Sign the agreement
                    </h1>
                    <p className="text-muted-foreground">
                        {signed
                            ? 'This is the agreement you signed. Keep this page bookmarked — you can come back anytime.'
                            : `Read the agreement below for ${estimate.title}. When you're ready, type your name and confirm at the bottom.`}
                    </p>
                </header>

                {signed && (
                    <section className="flex items-start gap-3 rounded-xl border-2 border-emerald-500/60 bg-emerald-50 p-4 dark:bg-emerald-950/40">
                        <CircleCheck className="size-5 shrink-0 text-emerald-600" />
                        <div className="text-sm text-emerald-900 dark:text-emerald-100">
                            <span className="font-semibold">
                                Signed by {signed.name}
                            </span>{' '}
                            on {formatDate(signed.signed_at)}.
                        </div>
                    </section>
                )}

                {!signed && (
                    <section className="rounded-xl border bg-card p-5">
                        <h2 className="mb-3 text-base font-semibold">
                            You are agreeing to pay
                        </h2>
                        <div className="space-y-1 text-sm">
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
                            <div className="grid grid-cols-[1fr_auto] gap-3 pt-2 text-sm">
                                <span className="text-muted-foreground">
                                    Deposit due today ({quote.material_percent}%
                                    materials, {quote.labor_percent}% labor)
                                </span>
                                <span className="font-semibold tabular-nums">
                                    {formatMoney(quote.deposit_total)}
                                </span>
                            </div>
                        </div>
                    </section>
                )}

                <article
                    className="prose prose-sm dark:prose-invert max-w-none"
                    dangerouslySetInnerHTML={{
                        __html: contract.body_html,
                    }}
                />

                {!signed && (
                    <Form
                        {...ContractSigningController.store.form(
                            approval_token,
                        )}
                        options={{ preserveScroll: false }}
                        className="space-y-5 rounded-xl border bg-card p-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div>
                                    <h2 className="text-base font-semibold">
                                        Confirm and sign
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Your typed name and timestamp are
                                        recorded with the agreement.
                                    </p>
                                </div>

                                <input
                                    type="hidden"
                                    name="quote_hash"
                                    value={quote_hash}
                                />

                                <div className="grid gap-2">
                                    <Label htmlFor="name">
                                        Type your full name
                                    </Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        type="text"
                                        required
                                        minLength={2}
                                        maxLength={120}
                                        autoComplete="name"
                                        placeholder="Your full name"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="flex items-start gap-2">
                                    <Checkbox
                                        id="acknowledged"
                                        name="acknowledged"
                                        value="1"
                                        required
                                    />
                                    <Label
                                        htmlFor="acknowledged"
                                        className="text-sm leading-snug font-normal"
                                    >
                                        I have read and agree to the agreement
                                        above and the figures shown.
                                    </Label>
                                </div>
                                <InputError message={errors.acknowledged} />
                                <InputError message={errors.quote_hash} />

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="h-11 px-5"
                                >
                                    <Check className="mr-1 size-4" />
                                    Sign agreement
                                </Button>
                            </>
                        )}
                    </Form>
                )}

                {signed && (
                    <div>
                        <Button variant="outline" asChild>
                            <Link href={backUrl}>
                                <ArrowLeft className="mr-1 size-4" />
                                Back to overview
                            </Link>
                        </Button>
                    </div>
                )}
            </div>
        </QuoteLayout>
    );
}
