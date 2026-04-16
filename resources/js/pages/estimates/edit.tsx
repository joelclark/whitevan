import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, Loader2, RefreshCw } from 'lucide-react';
import { useEffect } from 'react';
import EstimateController from '@/actions/App/Http/Controllers/EstimateController';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { index as estimatesIndex } from '@/routes/estimates';
import type {
    Auth,
    Estimate,
    EstimateLineItem,
    EstimateRoom,
    FloorplanPagePreview,
    InterviewProps,
} from '@/types';
import EstimateRecordHeader from './estimate-record-header';
import LineItemsPanel from './line-items-panel';
import RoomsList from './rooms-list';

type Props = {
    estimate: Estimate & { rooms: EstimateRoom[] };
    interview: InterviewProps | null;
    floorplan_pages: FloorplanPagePreview[];
    line_items: EstimateLineItem[];
};

export default function EstimatesEdit({
    estimate,
    interview,
    floorplan_pages,
    line_items,
}: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const isProcessing = estimate.status === 'processing';
    const isRenderingFloorplans =
        estimate.floorplan_assets_status === 'pending';
    const shouldPoll = isProcessing || isRenderingFloorplans;
    const canViewDebugLog = Boolean(auth.impersonating);

    useEffect(() => {
        if (!shouldPoll) {
            return;
        }

        const interval = window.setInterval(() => {
            router.reload({
                only: [
                    'estimate',
                    'interview',
                    'floorplan_pages',
                    'line_items',
                ],
            });
        }, 2000);

        return () => {
            window.clearInterval(interval);
        };
    }, [shouldPoll]);

    const displayTitle = estimate.title ?? estimate.pdf_original_filename;

    return (
        <>
            <Head title={displayTitle} />

            <div>
                <div className="mb-4">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={estimatesIndex()}>
                            <ArrowLeft className="mr-1 h-4 w-4" />
                            Back to estimates
                        </Link>
                    </Button>
                </div>

                <EstimateRecordHeader
                    estimate={estimate}
                    allLineItemsPriced={
                        line_items.length > 0 &&
                        line_items.every((item) => item.unit_price !== null)
                    }
                />

                <div className="mt-8 max-w-4xl space-y-8">
                    <section>
                        <h2 className="mb-4 text-lg font-semibold">Rooms</h2>

                        {estimate.status === 'processing' && (
                            <div className="space-y-4">
                                <div className="flex items-center gap-3 text-muted-foreground">
                                    <Loader2 className="h-5 w-5 animate-spin" />
                                    <p>
                                        Analyzing your floor plan… this usually
                                        takes 10–30 seconds.
                                    </p>
                                </div>
                                <div className="space-y-2">
                                    {Array.from({ length: 4 }).map((_, i) => (
                                        <Skeleton
                                            key={i}
                                            className="h-14 w-full rounded-xl"
                                        />
                                    ))}
                                </div>
                            </div>
                        )}

                        {estimate.status === 'ready' && (
                            <RoomsList
                                estimate={estimate}
                                interview={interview}
                                floorplanPages={floorplan_pages}
                            />
                        )}

                        {estimate.status === 'failed' && (
                            <div className="rounded-xl border border-destructive/40 bg-destructive/5 p-6">
                                <div className="mb-3 flex items-center gap-2 text-destructive">
                                    <AlertCircle className="h-5 w-5" />
                                    <p className="font-semibold">
                                        Floor plan extraction failed
                                    </p>
                                </div>
                                {estimate.agent_errors.length > 0 ? (
                                    <div className="space-y-2">
                                        {estimate.agent_errors.map(
                                            (error, i) => (
                                                <details
                                                    key={i}
                                                    className="group rounded-md border border-destructive/30 bg-background/40 open:bg-background"
                                                >
                                                    <summary className="flex cursor-pointer items-start gap-2 px-3 py-2 text-sm text-muted-foreground marker:content-none">
                                                        <span className="mt-0.5 text-destructive">
                                                            ▸
                                                        </span>
                                                        <span className="flex-1 truncate group-open:whitespace-normal">
                                                            {error}
                                                        </span>
                                                    </summary>
                                                    <pre className="max-h-96 overflow-auto border-t border-destructive/20 bg-background/60 px-3 py-3 font-mono text-xs leading-relaxed break-all whitespace-pre-wrap text-foreground">
                                                        {error}
                                                    </pre>
                                                </details>
                                            ),
                                        )}
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        The agent didn&apos;t return any details
                                        about what went wrong.
                                    </p>
                                )}
                                <Form
                                    {...EstimateController.retry.form(
                                        estimate.id,
                                    )}
                                    className="mt-5"
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            className="h-12 px-5 text-base"
                                        >
                                            <RefreshCw
                                                className={`mr-2 h-4 w-4 ${
                                                    processing
                                                        ? 'animate-spin'
                                                        : ''
                                                }`}
                                            />
                                            {processing
                                                ? 'Retrying…'
                                                : 'Retry extraction'}
                                        </Button>
                                    )}
                                </Form>
                            </div>
                        )}
                    </section>

                    {line_items.length > 0 && (
                        <section>
                            <h2 className="mb-4 text-lg font-semibold">
                                Line Items
                            </h2>
                            <LineItemsPanel
                                estimateId={estimate.id}
                                lineItems={line_items}
                            />
                        </section>
                    )}

                    {canViewDebugLog && estimate.debug_log && (
                        <details className="group rounded-xl border bg-muted/10 open:bg-muted/20">
                            <summary className="flex cursor-pointer items-center justify-between gap-3 px-6 py-4 text-sm font-semibold marker:content-none">
                                <span>
                                    AI agent debug log{' '}
                                    <span className="font-normal text-muted-foreground">
                                        (request, schema, and raw response)
                                    </span>
                                </span>
                                <span className="text-muted-foreground transition-transform group-open:rotate-90">
                                    ▸
                                </span>
                            </summary>
                            <pre className="max-h-[32rem] overflow-auto border-t bg-background/60 px-6 py-4 font-mono text-xs leading-relaxed break-all whitespace-pre-wrap text-foreground">
                                {JSON.stringify(estimate.debug_log, null, 2)}
                            </pre>
                        </details>
                    )}
                </div>
            </div>
        </>
    );
}

EstimatesEdit.layout = {
    breadcrumbs: [{ title: 'Estimates', href: estimatesIndex() }],
};
