<?php

namespace App\Services;

use App\Enums\LineItemKind;
use App\Models\Estimate;

/**
 * Builds the canonical, in-memory view of an estimate's quote that the
 * customer sees on the approval page and signs against. Also provides a
 * stable hash of that view used for render-to-commit change detection —
 * the approval page embeds the hash, the signing POST recomputes it, a
 * mismatch means the quote moved under the customer and the sign is
 * rejected.
 *
 * "Snapshot" here is purely the computed view: nothing is persisted.
 */
class QuoteSnapshot
{
    public function __construct(private DepositResolver $resolver) {}

    /**
     * @return array{
     *   material_percent: int,
     *   labor_percent: int,
     *   items: array<int, array{
     *     id: int,
     *     kind: string,
     *     label: string,
     *     category: string,
     *     quantity: string,
     *     unit: string,
     *     unit_price: string,
     *     notes: ?string,
     *     line_total: string,
     *   }>,
     *   material_subtotal: string,
     *   labor_subtotal: string,
     *   grand_total: string,
     *   material_deposit: string,
     *   labor_deposit: string,
     *   deposit_total: string,
     * }
     */
    public function build(Estimate $estimate): array
    {
        $materialPercent = $this->resolver->materialPercentFor($estimate->account);
        $laborPercent = $this->resolver->laborPercentFor($estimate->account);

        $items = $estimate->activeLineItems()
            ->orderBy('id')
            ->get()
            ->map(function ($item): array {
                $quantity = $item->quantity ?? '0.00';
                $unitPrice = $item->unit_price ?? '0.00';
                $lineTotal = $this->money((float) $quantity * (float) $unitPrice);

                return [
                    'id' => $item->id,
                    'kind' => $item->kind->value,
                    'label' => $item->label,
                    'category' => $item->category->value,
                    'quantity' => (string) $quantity,
                    'unit' => $item->unit->value,
                    'unit_price' => (string) $unitPrice,
                    'notes' => $item->notes,
                    'line_total' => $lineTotal,
                ];
            })
            ->values()
            ->all();

        $materialSubtotal = $this->sumFor($items, LineItemKind::Material->value);
        $laborSubtotal = $this->sumFor($items, LineItemKind::Labor->value);
        $grandTotal = $this->money((float) $materialSubtotal + (float) $laborSubtotal);

        $materialDeposit = $this->money((float) $materialSubtotal * $materialPercent / 100);
        $laborDeposit = $this->money((float) $laborSubtotal * $laborPercent / 100);
        $depositTotal = $this->money((float) $materialDeposit + (float) $laborDeposit);

        return [
            'material_percent' => $materialPercent,
            'labor_percent' => $laborPercent,
            'items' => $items,
            'material_subtotal' => $materialSubtotal,
            'labor_subtotal' => $laborSubtotal,
            'grand_total' => $grandTotal,
            'material_deposit' => $materialDeposit,
            'labor_deposit' => $laborDeposit,
            'deposit_total' => $depositTotal,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<int, array{kind: string, line_total: string}>  $items
     */
    private function sumFor(array $items, string $kind): string
    {
        $sum = 0.0;
        foreach ($items as $item) {
            if ($item['kind'] === $kind) {
                $sum += (float) $item['line_total'];
            }
        }

        return $this->money($sum);
    }

    private function money(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }
}
