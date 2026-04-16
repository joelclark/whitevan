import { Head } from '@inertiajs/react';
import { Info } from 'lucide-react';
import QuoteLayout from '@/layouts/quote-layout';
import type { EstimateLineItem } from '@/types';

type Room = {
    id: number;
    name: string;
    sqft: number;
    linear_feet: number;
    page: number;
};

type FloorplanPage = {
    page: number;
    width: number;
    height: number;
    url: string;
};

type Props = {
    estimate: {
        title: string;
        total_sqft: number | null;
        trade: string;
        quote_sent_at: string | null;
    };
    customer: {
        first_name: string;
        last_name: string;
        company: string | null;
    };
    account_name: string;
    rooms: Room[];
    floorplan_pages: FloorplanPage[];
    line_items: EstimateLineItem[];
    has_changed: boolean;
};

type GroupedItems = Record<
    string,
    { label: string; items: EstimateLineItem[] }
>;

function groupByCategory(items: EstimateLineItem[]): GroupedItems {
    const groups: GroupedItems = {};

    for (const item of items) {
        if (!groups[item.category]) {
            groups[item.category] = {
                label: item.category_label,
                items: [],
            };
        }

        groups[item.category].items.push(item);
    }

    return groups;
}

function formatQuantity(item: EstimateLineItem): string {
    const qty = Number.isInteger(item.quantity)
        ? item.quantity.toString()
        : item.quantity.toFixed(2);

    return `${qty} ${item.unit}`;
}

function formatCurrency(value: number): string {
    return value.toLocaleString('en-US', {
        style: 'currency',
        currency: 'USD',
    });
}

function formatDate(value: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleDateString(undefined, {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

export default function QuoteShow({
    estimate,
    customer,
    account_name,
    rooms,
    floorplan_pages,
    line_items,
    has_changed,
}: Props) {
    const customerName = `${customer.first_name} ${customer.last_name}`.trim();
    const groups = groupByCategory(line_items);

    const grandTotal = line_items.reduce((sum, item) => {
        if (item.unit_price !== null) {
            return sum + item.unit_price * item.quantity;
        }

        return sum;
    }, 0);

    const allPriced = line_items.every((item) => item.unit_price !== null);

    const floorplanByPage = new Map(floorplan_pages.map((fp) => [fp.page, fp]));

    return (
        <QuoteLayout accountName={account_name}>
            <Head title={`Quote — ${estimate.title}`} />

            <div className="space-y-8">
                {has_changed && (
                    <div className="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-950/50 dark:text-blue-200">
                        <Info className="mt-0.5 h-4 w-4 shrink-0" />
                        <span>
                            This quote has been updated since you last viewed
                            it.
                        </span>
                    </div>
                )}

                <header className="space-y-2">
                    <h1 className="text-3xl font-semibold tracking-tight">
                        {estimate.title}
                    </h1>
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
                        <span>Prepared for {customerName}</span>
                        {customer.company && <span>{customer.company}</span>}
                        {estimate.total_sqft !== null && (
                            <span>
                                {estimate.total_sqft.toLocaleString()} sq ft
                            </span>
                        )}
                        {estimate.quote_sent_at && (
                            <span>{formatDate(estimate.quote_sent_at)}</span>
                        )}
                    </div>
                </header>

                {rooms.length > 0 && (
                    <section>
                        <h2 className="mb-4 text-lg font-semibold">Rooms</h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            {rooms.map((room) => {
                                const fp = floorplanByPage.get(room.page);

                                return (
                                    <div
                                        key={room.id}
                                        className="overflow-hidden rounded-xl border bg-card"
                                    >
                                        {fp && (
                                            <img
                                                src={fp.url}
                                                alt={`Floor plan — ${room.name}`}
                                                width={fp.width}
                                                height={fp.height}
                                                className="w-full border-b bg-muted/30 object-contain"
                                            />
                                        )}
                                        <div className="px-4 py-3">
                                            <p className="font-medium">
                                                {room.name}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {room.sqft.toLocaleString()} sq
                                                ft
                                                {room.linear_feet > 0 &&
                                                    ` · ${room.linear_feet.toLocaleString()} lf`}
                                            </p>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </section>
                )}

                {line_items.length > 0 && (
                    <section>
                        <h2 className="mb-4 text-lg font-semibold">
                            Line Items
                        </h2>
                        <div className="rounded-xl border bg-card">
                            <div className="grid grid-cols-[1fr_auto_auto_auto] gap-3 border-b px-4 py-2 text-xs font-medium text-muted-foreground">
                                <span>Item</span>
                                <span>Qty</span>
                                <span className="w-24 text-right">
                                    Unit Price
                                </span>
                                <span className="w-24 text-right">Total</span>
                            </div>

                            {Object.entries(groups).map(([category, group]) => (
                                <div key={category}>
                                    <div className="border-b bg-muted/30 px-4 py-1.5">
                                        <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                            {group.label}
                                        </span>
                                    </div>
                                    <div className="divide-y">
                                        {group.items.map((item) => {
                                            const lineTotal =
                                                item.unit_price !== null
                                                    ? item.unit_price *
                                                      item.quantity
                                                    : null;

                                            return (
                                                <div
                                                    key={item.key}
                                                    className="grid grid-cols-[1fr_auto_auto_auto] items-center gap-3 px-4 py-2.5"
                                                >
                                                    <span className="text-sm">
                                                        {item.label}
                                                    </span>
                                                    <span className="text-sm text-muted-foreground tabular-nums">
                                                        {formatQuantity(item)}
                                                    </span>
                                                    <span className="w-24 text-right text-sm tabular-nums">
                                                        {item.unit_price !==
                                                        null
                                                            ? formatCurrency(
                                                                  item.unit_price,
                                                              )
                                                            : ''}
                                                    </span>
                                                    <span className="w-24 text-right text-sm text-muted-foreground tabular-nums">
                                                        {lineTotal !== null
                                                            ? formatCurrency(
                                                                  lineTotal,
                                                              )
                                                            : ''}
                                                    </span>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}

                            {allPriced && (
                                <div className="grid grid-cols-[1fr_auto_auto_auto] items-center gap-3 border-t bg-muted/20 px-4 py-3">
                                    <span className="text-base font-semibold">
                                        Total
                                    </span>
                                    <span />
                                    <span />
                                    <span className="w-24 text-right text-base font-semibold tabular-nums">
                                        {formatCurrency(grandTotal)}
                                    </span>
                                </div>
                            )}
                        </div>
                    </section>
                )}
            </div>
        </QuoteLayout>
    );
}
