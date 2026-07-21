import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import EstimateController from '@/actions/App/Http/Controllers/EstimateController';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { EstimateLineItem } from '@/types';

type DepositDefaults = {
    material_deposit_percent: number;
    labor_deposit_percent: number;
};

type Props = {
    estimateId: number;
    lineItems: EstimateLineItem[];
    depositDefaults: DepositDefaults;
    isLocked: boolean;
};

type Pair = {
    kind: 'pair';
    stem: string;
    title: string;
    material: EstimateLineItem;
    labor: EstimateLineItem;
};

type Solo = {
    kind: 'solo';
    item: EstimateLineItem;
};

type Group = Pair | Solo;

type CategoryBlock = {
    key: string;
    label: string;
    groups: Group[];
};

// Grid columns: item/label | qty | unit price | total
const GRID_COLS = 'grid-cols-[1fr_5.5rem_7rem_7rem]';

function stemOf(key: string): string {
    return key.replace(/_(material|labor)$/, '');
}

function titleOfPair(material: EstimateLineItem): string {
    return material.label.replace(/ — materials$/i, '').trim();
}

function buildCategoryBlocks(items: EstimateLineItem[]): CategoryBlock[] {
    const byCategory = new Map<
        string,
        { label: string; items: EstimateLineItem[] }
    >();

    for (const item of items) {
        if (!byCategory.has(item.category)) {
            byCategory.set(item.category, {
                label: item.category_label,
                items: [],
            });
        }

        byCategory.get(item.category)!.items.push(item);
    }

    const blocks: CategoryBlock[] = [];

    for (const [cat, { label, items: catItems }] of byCategory) {
        const stems = new Map<string, EstimateLineItem[]>();

        for (const item of catItems) {
            const stem = stemOf(item.key);

            if (!stems.has(stem)) {
                stems.set(stem, []);
            }

            stems.get(stem)!.push(item);
        }

        const groups: Group[] = [];

        for (const [stem, stemItems] of stems) {
            const material = stemItems.find((i) => i.kind === 'material');
            const labor = stemItems.find((i) => i.kind === 'labor');

            if (material && labor && stemItems.length === 2) {
                groups.push({
                    kind: 'pair',
                    stem,
                    title: titleOfPair(material),
                    material,
                    labor,
                });
            } else {
                for (const item of stemItems) {
                    groups.push({ kind: 'solo', item });
                }
            }
        }

        blocks.push({ key: cat, label, groups });
    }

    return blocks;
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
    disabled,
}: {
    estimateId: number;
    item: EstimateLineItem;
    disabled: boolean;
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
            disabled={disabled || saving}
            className="ml-auto h-7 w-24 px-2 text-right text-sm tabular-nums"
        />
    );
}

function NotesInput({
    estimateId,
    item,
    disabled,
}: {
    estimateId: number;
    item: EstimateLineItem;
    disabled: boolean;
}) {
    const [value, setValue] = useState(item.notes ?? '');
    const [saving, setSaving] = useState(false);
    const lastSaved = useRef(item.notes ?? '');

    function save() {
        if (value === lastSaved.current) {
            return;
        }

        setSaving(true);

        router.patch(
            EstimateController.updateLineItem.url({
                estimate: estimateId,
                lineItem: item.id,
            }),
            { notes: value === '' ? null : value },
            {
                preserveScroll: true,
                onSuccess: () => {
                    lastSaved.current = value;
                },
                onError: () => {
                    setValue(lastSaved.current);
                },
                onFinish: () => setSaving(false),
            },
        );
    }

    return (
        <input
            type="text"
            placeholder="Brand, variant, color…"
            value={value}
            onChange={(e) => setValue(e.target.value)}
            onBlur={save}
            onKeyDown={(e) => {
                if (e.key === 'Enter') {
                    e.currentTarget.blur();
                }
            }}
            maxLength={500}
            disabled={disabled || saving}
            className={cn(
                'w-full border-0 bg-transparent px-0 py-0 text-xs text-foreground italic placeholder:text-muted-foreground/70',
                'border-b border-transparent transition-colors focus:border-primary/60 focus:ring-0 focus:outline-none',
                'not-italic',
            )}
        />
    );
}

function ReusedHint({ item }: { item: EstimateLineItem }) {
    if (!item.price_prefilled || item.unit_price === null) {
        return null;
    }

    return (
        <span
            title="Reused from a previous estimate — edit to override"
            className="rounded-sm bg-muted px-1 py-0.5 text-[10px] leading-none font-medium text-muted-foreground"
        >
            reused
        </span>
    );
}

function RoleDot({ role }: { role: 'material' | 'labor' }) {
    return (
        <span
            className={cn(
                'inline-block h-1.5 w-1.5 shrink-0 rounded-full',
                role === 'material' ? 'bg-amber-400' : 'bg-sky-400',
            )}
            aria-hidden
        />
    );
}

function SubRow({
    estimateId,
    item,
    role,
    isLocked,
}: {
    estimateId: number;
    item: EstimateLineItem;
    role: 'material' | 'labor';
    isLocked: boolean;
}) {
    const lineTotal =
        item.unit_price !== null ? item.unit_price * item.quantity : null;

    return (
        <div className={cn('grid items-center gap-3 px-4 py-1.5', GRID_COLS)}>
            <div className="flex min-w-0 items-center gap-2 pl-6">
                <RoleDot role={role} />
                <span className="text-sm text-muted-foreground capitalize">
                    {role}
                </span>
                {!isLocked && <ReusedHint item={item} />}
            </div>
            <span className="text-right text-sm text-muted-foreground tabular-nums">
                {formatQuantity(item)}
            </span>
            <div className="flex justify-end">
                {isLocked ? (
                    <span className="text-right text-sm tabular-nums">
                        {item.unit_price !== null
                            ? formatCurrency(item.unit_price)
                            : '—'}
                    </span>
                ) : (
                    <PriceInput
                        estimateId={estimateId}
                        item={item}
                        disabled={isLocked}
                    />
                )}
            </div>
            <span className="text-right text-sm tabular-nums">
                {lineTotal !== null ? (
                    formatCurrency(lineTotal)
                ) : (
                    <span className="text-muted-foreground">—</span>
                )}
            </span>
        </div>
    );
}

function PairGroup({
    group,
    estimateId,
    isLocked,
}: {
    group: Pair;
    estimateId: number;
    isLocked: boolean;
}) {
    return (
        <div className="py-2">
            <div className="px-4 py-0.5">
                <span className="text-sm font-medium">{group.title}</span>
            </div>

            <SubRow
                estimateId={estimateId}
                item={group.material}
                role="material"
                isLocked={isLocked}
            />

            {!isLocked && (
                <div className="px-4 pb-1.5 pl-[3.5rem]">
                    <NotesInput
                        estimateId={estimateId}
                        item={group.material}
                        disabled={isLocked}
                    />
                </div>
            )}
            {isLocked && group.material.notes && (
                <div className="px-4 pb-1.5 pl-[3.5rem] text-xs text-muted-foreground italic">
                    {group.material.notes}
                </div>
            )}

            <SubRow
                estimateId={estimateId}
                item={group.labor}
                role="labor"
                isLocked={isLocked}
            />
        </div>
    );
}

function SoloRow({
    item,
    estimateId,
    isLocked,
}: {
    item: EstimateLineItem;
    estimateId: number;
    isLocked: boolean;
}) {
    const lineTotal =
        item.unit_price !== null ? item.unit_price * item.quantity : null;

    return (
        <div className={cn('grid items-center gap-3 px-4 py-2.5', GRID_COLS)}>
            <div className="flex min-w-0 items-center gap-2">
                <RoleDot role={item.kind} />
                <span className="text-sm">{item.label}</span>
                {!isLocked && <ReusedHint item={item} />}
            </div>
            <span className="text-right text-sm text-muted-foreground tabular-nums">
                {formatQuantity(item)}
            </span>
            <div className="flex justify-end">
                {isLocked ? (
                    <span className="text-right text-sm tabular-nums">
                        {item.unit_price !== null
                            ? formatCurrency(item.unit_price)
                            : '—'}
                    </span>
                ) : (
                    <PriceInput
                        estimateId={estimateId}
                        item={item}
                        disabled={isLocked}
                    />
                )}
            </div>
            <span className="text-right text-sm tabular-nums">
                {lineTotal !== null ? (
                    formatCurrency(lineTotal)
                ) : (
                    <span className="text-muted-foreground">—</span>
                )}
            </span>
        </div>
    );
}

export default function LineItemsPanel({
    estimateId,
    lineItems,
    depositDefaults,
    isLocked,
}: Props) {
    if (lineItems.length === 0) {
        return null;
    }

    const blocks = buildCategoryBlocks(lineItems);

    const materialSubtotal = lineItems.reduce((sum, item) => {
        if (item.kind === 'material' && item.unit_price !== null) {
            return sum + item.unit_price * item.quantity;
        }

        return sum;
    }, 0);

    const laborSubtotal = lineItems.reduce((sum, item) => {
        if (item.kind === 'labor' && item.unit_price !== null) {
            return sum + item.unit_price * item.quantity;
        }

        return sum;
    }, 0);

    const grandTotal = materialSubtotal + laborSubtotal;

    const allPriced = lineItems.every((item) => item.unit_price !== null);
    const materialDeposit =
        materialSubtotal * (depositDefaults.material_deposit_percent / 100);
    const laborDeposit =
        laborSubtotal * (depositDefaults.labor_deposit_percent / 100);
    const depositTotal = materialDeposit + laborDeposit;

    return (
        <div className="space-y-4">
            {isLocked && (
                <div className="rounded-lg border border-muted-foreground/20 bg-muted/30 px-4 py-3 text-sm text-muted-foreground">
                    This quote has been accepted and locked. Further changes
                    require creating a new estimate on this project.
                </div>
            )}

            <div className="overflow-hidden rounded-xl border bg-card">
                <div
                    className={cn(
                        'grid gap-3 border-b px-4 py-2 text-xs font-medium text-muted-foreground',
                        GRID_COLS,
                    )}
                >
                    <span>Item</span>
                    <span className="text-right">Qty</span>
                    <span className="text-right">Unit Price</span>
                    <span className="text-right">Total</span>
                </div>

                {blocks.map((block) => (
                    <div key={block.key} className="border-b last:border-b-0">
                        <div className="bg-muted/30 px-4 py-1.5">
                            <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                {block.label}
                            </span>
                        </div>
                        <div className="divide-y">
                            {block.groups.map((group) =>
                                group.kind === 'pair' ? (
                                    <PairGroup
                                        key={group.stem}
                                        group={group}
                                        estimateId={estimateId}
                                        isLocked={isLocked}
                                    />
                                ) : (
                                    <SoloRow
                                        key={group.item.key}
                                        item={group.item}
                                        estimateId={estimateId}
                                        isLocked={isLocked}
                                    />
                                ),
                            )}
                        </div>
                    </div>
                ))}

                {allPriced && (
                    <div className="space-y-1 border-t bg-muted/20 px-4 py-3">
                        <div className="grid grid-cols-[1fr_auto] items-center gap-3 text-sm">
                            <span className="text-muted-foreground">
                                Materials subtotal
                            </span>
                            <span className="w-28 text-right tabular-nums">
                                {formatCurrency(materialSubtotal)}
                            </span>
                        </div>
                        <div className="grid grid-cols-[1fr_auto] items-center gap-3 text-sm">
                            <span className="text-muted-foreground">
                                Labor subtotal
                            </span>
                            <span className="w-28 text-right tabular-nums">
                                {formatCurrency(laborSubtotal)}
                            </span>
                        </div>
                        <div className="grid grid-cols-[1fr_auto] items-center gap-3 border-t pt-2 text-sm font-semibold">
                            <span>Total</span>
                            <span className="w-28 text-right tabular-nums">
                                {formatCurrency(grandTotal)}
                            </span>
                        </div>
                    </div>
                )}
            </div>

            {allPriced && (
                <div className="rounded-xl border bg-card p-4">
                    <h3 className="mb-3 text-sm font-semibold">
                        Deposit at signing
                    </h3>
                    <div className="space-y-1.5 text-sm">
                        <div className="grid grid-cols-[1fr_auto] items-center gap-3">
                            <span className="text-muted-foreground">
                                Materials (
                                {depositDefaults.material_deposit_percent}%)
                            </span>
                            <span className="w-28 text-right tabular-nums">
                                {formatCurrency(materialDeposit)}
                            </span>
                        </div>
                        <div className="grid grid-cols-[1fr_auto] items-center gap-3">
                            <span className="text-muted-foreground">
                                Labor ({depositDefaults.labor_deposit_percent}%)
                            </span>
                            <span className="w-28 text-right tabular-nums">
                                {formatCurrency(laborDeposit)}
                            </span>
                        </div>
                        <div className="grid grid-cols-[1fr_auto] items-center gap-3 border-t pt-2 font-semibold">
                            <span>Deposit due</span>
                            <span className="w-28 text-right tabular-nums">
                                {formatCurrency(depositTotal)}
                            </span>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
