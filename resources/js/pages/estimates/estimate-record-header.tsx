import { Form, Link } from '@inertiajs/react';
import {
    ExternalLink,
    FileText,
    MoreHorizontal,
    RefreshCw,
    Send,
    User,
} from 'lucide-react';
import { useState } from 'react';
import EstimateController from '@/actions/App/Http/Controllers/EstimateController';
import QuoteController from '@/actions/App/Http/Controllers/QuoteController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { edit as customersEdit } from '@/routes/customers';
import { pdf as estimatesPdf } from '@/routes/estimates';
import { show as quotesShow } from '@/routes/quotes';
import type { Estimate, EstimateStatus } from '@/types';
import DeleteEstimateDialog from './delete-estimate-dialog';
import ResubmitEstimateDialog from './resubmit-estimate-dialog';

type Props = {
    estimate: Estimate;
    allLineItemsPriced: boolean;
};

function statusBadge(status: EstimateStatus) {
    switch (status) {
        case 'processing':
            return (
                <Badge variant="secondary" className="animate-pulse">
                    Processing
                </Badge>
            );
        case 'ready':
            return <Badge variant="default">Ready</Badge>;
        case 'failed':
            return <Badge variant="destructive">Failed</Badge>;
    }
}

export default function EstimateRecordHeader({
    estimate,
    allLineItemsPriced,
}: Props) {
    const [isEditingTitle, setIsEditingTitle] = useState(false);

    const customer = estimate.customer;
    const customerName = customer
        ? `${customer.first_name} ${customer.last_name}`.trim()
        : 'Unknown customer';

    return (
        <div className="flex flex-col gap-4 border-b pb-6 md:flex-row md:items-start md:justify-between">
            <div className="min-w-0 flex-1 space-y-2">
                {isEditingTitle ? (
                    <Form
                        {...EstimateController.update.form(estimate.id)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setIsEditingTitle(false)}
                        className="space-y-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Input
                                    name="title"
                                    defaultValue={estimate.title ?? ''}
                                    autoFocus
                                    placeholder="Estimate title"
                                    className="h-12 text-xl font-semibold"
                                />
                                <InputError message={errors.title} />
                                <div className="flex gap-2">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="h-10"
                                    >
                                        Save
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        className="h-10"
                                        onClick={() => setIsEditingTitle(false)}
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                ) : (
                    <button
                        type="button"
                        onClick={() => setIsEditingTitle(true)}
                        className="block max-w-full truncate text-left text-3xl font-semibold tracking-tight hover:text-muted-foreground"
                        title="Tap to edit the title"
                    >
                        {estimate.title ?? estimate.pdf_original_filename}
                    </button>
                )}

                <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted-foreground">
                    {statusBadge(estimate.status)}
                    {customer && (
                        <Link
                            href={customersEdit(customer.id)}
                            className="inline-flex items-center gap-1.5 hover:text-foreground"
                        >
                            <User className="h-4 w-4" />
                            {customerName}
                        </Link>
                    )}
                    {estimate.total_sqft !== null && (
                        <span>
                            <span className="font-semibold text-foreground">
                                {estimate.total_sqft.toLocaleString()}
                            </span>{' '}
                            total sq ft
                        </span>
                    )}
                </div>
            </div>

            <div className="flex flex-shrink-0 items-center gap-2">
                <Button
                    asChild
                    variant="outline"
                    className="h-12 px-5 text-base"
                >
                    <a
                        href={estimatesPdf(estimate.id).url}
                        target="_blank"
                        rel="noreferrer"
                    >
                        <FileText className="mr-2 h-4 w-4" />
                        Open PDF
                    </a>
                </Button>

                {estimate.status === 'ready' &&
                    !estimate.quote_status &&
                    allLineItemsPriced && (
                        <Form
                            {...QuoteController.send.form(estimate.id)}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="h-12 px-5 text-base"
                                >
                                    <Send className="mr-2 h-4 w-4" />
                                    {processing ? 'Sending...' : 'Send Quote'}
                                </Button>
                            )}
                        </Form>
                    )}

                {estimate.quote_status === 'sent' && estimate.quote_token && (
                    <Button
                        asChild
                        variant="outline"
                        className="h-12 px-5 text-base"
                    >
                        <a
                            href={quotesShow(estimate.quote_token).url}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <ExternalLink className="mr-2 h-4 w-4" />
                            View Quote
                        </a>
                    </Button>
                )}

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="outline"
                            size="icon"
                            type="button"
                            className="h-12 w-12"
                        >
                            <MoreHorizontal className="h-5 w-5" />
                            <span className="sr-only">More actions</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        {estimate.status !== 'processing' && (
                            <ResubmitEstimateDialog
                                estimate={estimate}
                                trigger={
                                    <DropdownMenuItem
                                        onSelect={(e) => e.preventDefault()}
                                    >
                                        <RefreshCw className="mr-2 h-4 w-4" />
                                        Resubmit PDF
                                    </DropdownMenuItem>
                                }
                            />
                        )}
                        <DeleteEstimateDialog
                            estimate={estimate}
                            trigger={
                                <DropdownMenuItem
                                    onSelect={(e) => e.preventDefault()}
                                    className="text-destructive focus:text-destructive"
                                >
                                    Delete estimate
                                </DropdownMenuItem>
                            }
                        />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    );
}
