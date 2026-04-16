import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import EstimateController from '@/actions/App/Http/Controllers/EstimateController';
import { Input } from '@/components/ui/input';
import type { EstimateLineItem } from '@/types';

type Props = {
    estimateId: number;
    lineItems: EstimateLineItem[];
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

function PriceInput({
    estimateId,
    item,
}: {
    estimateId: number;
    item: EstimateLineItem;
}) {
    const [value, setValue] = useState(
        item.unit_price !== null ? item.unit_price.toFixed(2) : '',
    );
    const [saving, setSaving] = useState(false);
    const lastSaved = useRef(item.unit_price);

    function save() {
        const parsed = value === '' ? null : parseFloat(value);

        if (parsed === lastSaved.current) {
            return;
        }

        if (value !== '' && (isNaN(parsed!) || parsed! < 0)) {
            setValue(
                lastSaved.current !== null ? lastSaved.current.toFixed(2) : '',
            );

            return;
        }

        setSaving(true);

        router.patch(
            EstimateController.updateLineItem.url({
                estimate: estimateId,
                lineItem: item.id,
            }),
            { unit_price: parsed },
            {
                preserveScroll: true,
                onSuccess: () => {
                    lastSaved.current = parsed;
                },
                onError: () => {
                    setValue(
                        lastSaved.current !== null
                            ? lastSaved.current.toFixed(2)
                            : '',
                    );
                },
                onFinish: () => setSaving(false),
            },
        );
    }

    return (
        <Input
            type="number"
            inputMode="decimal"
            step="0.01"
            min="0"
            placeholder="—"
            value={value}
            onChange={(e) => setValue(e.target.value)}
            onBlur={save}
            onKeyDown={(e) => {
                if (e.key === 'Enter') {
                    e.currentTarget.blur();
                }
            }}
            disabled={saving}
            className="h-7 w-24 px-2 text-right text-sm tabular-nums"
        />
    );
}

function LineItemRow({
    estimateId,
    item,
}: {
    estimateId: number;
    item: EstimateLineItem;
}) {
    const lineTotal =
        item.unit_price !== null ? item.unit_price * item.quantity : null;

    return (
        <div className="grid grid-cols-[1fr_auto_auto_auto] items-center gap-3 px-4 py-2.5">
            <span className="text-sm">{item.label}</span>
            <span className="text-sm text-muted-foreground tabular-nums">
                {formatQuantity(item)}
            </span>
            <PriceInput estimateId={estimateId} item={item} />
            <span className="w-24 text-right text-sm text-muted-foreground tabular-nums">
                {lineTotal !== null ? formatCurrency(lineTotal) : ''}
            </span>
        </div>
    );
}

export default function LineItemsPanel({ estimateId, lineItems }: Props) {
    if (lineItems.length === 0) {
        return null;
    }

    const groups = groupByCategory(lineItems);

    const grandTotal = lineItems.reduce((sum, item) => {
        if (item.unit_price !== null) {
            return sum + item.unit_price * item.quantity;
        }

        return sum;
    }, 0);

    const allPriced = lineItems.every((item) => item.unit_price !== null);

    return (
        <div className="space-y-4">
            <div className="rounded-xl border bg-card">
                <div className="grid grid-cols-[1fr_auto_auto_auto] gap-3 border-b px-4 py-2 text-xs font-medium text-muted-foreground">
                    <span>Item</span>
                    <span>Qty</span>
                    <span className="w-24 text-right">Unit Price</span>
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
                            {group.items.map((item) => (
                                <LineItemRow
                                    key={item.key}
                                    estimateId={estimateId}
                                    item={item}
                                />
                            ))}
                        </div>
                    </div>
                ))}

                {allPriced && (
                    <div className="grid grid-cols-[1fr_auto_auto_auto] items-center gap-3 border-t bg-muted/20 px-4 py-3">
                        <span className="text-sm font-semibold">Total</span>
                        <span />
                        <span />
                        <span className="w-24 text-right text-sm font-semibold tabular-nums">
                            {formatCurrency(grandTotal)}
                        </span>
                    </div>
                )}
            </div>
        </div>
    );
}
