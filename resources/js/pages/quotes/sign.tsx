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

type Props = {
    estimate: {
        title: string;
    };
    account_name: string;
    approval_token: string;
    contract: {
        body_html: string;
        signed: Signed | null;
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

export default function QuoteSign({
    estimate,
    account_name,
    approval_token,
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
                                        above.
                                    </Label>
                                </div>
                                <InputError message={errors.acknowledged} />

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
