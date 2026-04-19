<?php

namespace App\Trades\Flooring\LineItems;

use App\Enums\LineItemCategory;
use App\Enums\LineItemKind;
use App\Enums\LineItemUnit;
use App\Interviews\LineItemDraft;
use App\Interviews\TradeLineItemEmitter;
use App\Models\Estimate;

class FlooringLineItemEmitter implements TradeLineItemEmitter
{
    private const MATERIAL_LABELS = [
        'lvp' => 'LVP',
        'laminate' => 'Laminate',
        'engineered' => 'Engineered Hardwood',
        'solid_hardwood' => 'Solid Hardwood',
        'tile' => 'Tile',
        'carpet' => 'Carpet',
        'sheet_vinyl' => 'Sheet Vinyl',
    ];

    private const EXISTING_LABELS = [
        'bare' => 'Bare Subfloor',
        'carpet' => 'Carpet',
        'lvp' => 'LVP',
        'laminate' => 'Laminate',
        'hardwood' => 'Hardwood',
        'tile' => 'Tile',
        'sheet_vinyl' => 'Sheet Vinyl',
    ];

    private const SUBFLOOR_LABELS = [
        'minor_patch' => 'Minor Patch',
        'major_self_level' => 'Major Self-Level',
    ];

    private const BASEBOARD_LABELS = [
        'remove_reinstall' => 'Remove & Reinstall',
        'remove_replace' => 'Remove & Replace',
    ];

    private const QUARTER_ROUND_LABELS = [
        'new' => 'New',
        'reuse' => 'Reuse',
    ];

    private const HAUL_AWAY_LABELS = [
        'van' => 'Van',
        'dumpster' => 'Dumpster',
    ];

    /** @return list<LineItemDraft> */
    public function emit(Estimate $estimate): array
    {
        $estimate->loadMissing('rooms');
        $answers = $this->normalizeAnswers($estimate);
        $rooms = $estimate->rooms;
        $roomAnswers = $answers['rooms'];
        $projectWide = $answers['project_wide'];

        $items = [];

        $this->emitDemoItems($items, $rooms, $roomAnswers);
        $this->emitSubfloorItems($items, $rooms, $roomAnswers);
        $this->emitInstallItems($items, $rooms, $roomAnswers);
        $this->emitFurnitureItems($items, $rooms, $roomAnswers);
        $this->emitTrimItems($items, $rooms, $projectWide);
        $this->emitServiceItems($items, $projectWide);

        return $items;
    }

    /**
     * @param  list<LineItemDraft>  $items
     */
    private function emitDemoItems(array &$items, $rooms, array $roomAnswers): void
    {
        $sqftByExisting = [];

        foreach ($rooms as $room) {
            $existing = $roomAnswers[(string) $room->id]['existing'] ?? null;
            if ($existing === null || $existing === 'bare') {
                continue;
            }
            $sqftByExisting[$existing] = ($sqftByExisting[$existing] ?? 0) + $room->sqft;
        }

        foreach ($sqftByExisting as $existing => $sqft) {
            $label = self::EXISTING_LABELS[$existing] ?? $existing;
            $items[] = new LineItemDraft(
                key: "remove_{$existing}",
                label: "Remove {$label}",
                category: LineItemCategory::Demo,
                kind: LineItemKind::Labor,
                quantity: $sqft,
                unit: LineItemUnit::Sqft,
            );
        }
    }

    /**
     * @param  list<LineItemDraft>  $items
     */
    private function emitSubfloorItems(array &$items, $rooms, array $roomAnswers): void
    {
        $sqftByType = [];

        foreach ($rooms as $room) {
            $subfloor = $roomAnswers[(string) $room->id]['subfloor'] ?? null;
            if ($subfloor === null || $subfloor === 'none') {
                continue;
            }
            $sqftByType[$subfloor] = ($sqftByType[$subfloor] ?? 0) + $room->sqft;
        }

        foreach ($sqftByType as $type => $sqft) {
            $label = self::SUBFLOOR_LABELS[$type] ?? $type;
            $items[] = new LineItemDraft(
                key: "subfloor_{$type}",
                label: "Subfloor {$label}",
                category: LineItemCategory::Prep,
                kind: LineItemKind::Labor,
                quantity: $sqft,
                unit: LineItemUnit::Sqft,
            );
        }
    }

    /**
     * Install items always split into material + labor pairs, keyed
     * `install_{material}_material` / `install_{material}_labor`. A future
     * materials catalog will pre-fill unit_price on the material row without
     * changing the shape.
     *
     * @param  list<LineItemDraft>  $items
     */
    private function emitInstallItems(array &$items, $rooms, array $roomAnswers): void
    {
        $sqftByMaterial = [];

        foreach ($rooms as $room) {
            $material = $roomAnswers[(string) $room->id]['material'] ?? null;
            if ($material === null) {
                continue;
            }
            $sqftByMaterial[$material] = ($sqftByMaterial[$material] ?? 0) + $room->sqft;
        }

        foreach ($sqftByMaterial as $material => $sqft) {
            $label = self::MATERIAL_LABELS[$material] ?? $material;

            $items[] = new LineItemDraft(
                key: "install_{$material}_material",
                label: "{$label} — materials",
                category: LineItemCategory::Install,
                kind: LineItemKind::Material,
                quantity: $this->materialWithWaste($sqft, LineItemUnit::Sqft),
                unit: LineItemUnit::Sqft,
            );

            $items[] = new LineItemDraft(
                key: "install_{$material}_labor",
                label: "Install {$label} — labor",
                category: LineItemCategory::Install,
                kind: LineItemKind::Labor,
                quantity: $sqft,
                unit: LineItemUnit::Sqft,
            );
        }
    }

    /**
     * @param  list<LineItemDraft>  $items
     */
    private function emitFurnitureItems(array &$items, $rooms, array $roomAnswers): void
    {
        $moveReplaceCount = 0;
        $heavyTotal = 0;

        foreach ($rooms as $room) {
            $furniture = $roomAnswers[(string) $room->id]['furniture'] ?? null;

            if ($furniture === 'move_replace') {
                $moveReplaceCount++;
            } elseif ($furniture === 'heavy') {
                $heavyTotal += (int) ($roomAnswers[(string) $room->id]['heavy_count'] ?? 0);
            }
        }

        if ($moveReplaceCount > 0) {
            $items[] = new LineItemDraft(
                key: 'furniture_move_replace',
                label: 'Move & Replace Furniture',
                category: LineItemCategory::Services,
                kind: LineItemKind::Labor,
                quantity: $moveReplaceCount,
                unit: LineItemUnit::Each,
            );
        }

        if ($heavyTotal > 0) {
            $items[] = new LineItemDraft(
                key: 'furniture_heavy',
                label: 'Move Heavy Items',
                category: LineItemCategory::Services,
                kind: LineItemKind::Labor,
                quantity: $heavyTotal,
                unit: LineItemUnit::Each,
            );
        }
    }

    /**
     * Trim items split into material + labor when new material is added
     * (baseboards `remove_replace`, quarter round `new`, transitions). Reuse
     * variants stay labor-only since no new material is purchased.
     *
     * @param  list<LineItemDraft>  $items
     */
    private function emitTrimItems(array &$items, $rooms, array $projectWide): void
    {
        $totalLinearFeet = $rooms->sum('linear_feet');

        $baseboards = $projectWide['baseboards'] ?? null;
        if ($baseboards !== null && $baseboards !== 'leave' && $totalLinearFeet > 0) {
            $label = self::BASEBOARD_LABELS[$baseboards] ?? $baseboards;

            if ($baseboards === 'remove_replace') {
                $items[] = new LineItemDraft(
                    key: "baseboards_{$baseboards}_material",
                    label: "Baseboards {$label} — materials",
                    category: LineItemCategory::Trim,
                    kind: LineItemKind::Material,
                    quantity: $this->materialWithWaste($totalLinearFeet, LineItemUnit::LinearFeet),
                    unit: LineItemUnit::LinearFeet,
                );
                $items[] = new LineItemDraft(
                    key: "baseboards_{$baseboards}_labor",
                    label: "Baseboards {$label} — labor",
                    category: LineItemCategory::Trim,
                    kind: LineItemKind::Labor,
                    quantity: $totalLinearFeet,
                    unit: LineItemUnit::LinearFeet,
                );
            } else {
                $items[] = new LineItemDraft(
                    key: "baseboards_{$baseboards}",
                    label: "Baseboards {$label}",
                    category: LineItemCategory::Trim,
                    kind: LineItemKind::Labor,
                    quantity: $totalLinearFeet,
                    unit: LineItemUnit::LinearFeet,
                );
            }
        }

        $quarterRound = $projectWide['quarter_round'] ?? null;
        if ($quarterRound !== null && $quarterRound !== 'none' && $totalLinearFeet > 0) {
            $label = self::QUARTER_ROUND_LABELS[$quarterRound] ?? $quarterRound;

            if ($quarterRound === 'new') {
                $items[] = new LineItemDraft(
                    key: 'quarter_round_new_material',
                    label: 'Quarter Round (New) — materials',
                    category: LineItemCategory::Trim,
                    kind: LineItemKind::Material,
                    quantity: $this->materialWithWaste($totalLinearFeet, LineItemUnit::LinearFeet),
                    unit: LineItemUnit::LinearFeet,
                );
                $items[] = new LineItemDraft(
                    key: 'quarter_round_new_labor',
                    label: 'Quarter Round (New) — labor',
                    category: LineItemCategory::Trim,
                    kind: LineItemKind::Labor,
                    quantity: $totalLinearFeet,
                    unit: LineItemUnit::LinearFeet,
                );
            } else {
                $items[] = new LineItemDraft(
                    key: "quarter_round_{$quarterRound}",
                    label: "Quarter Round ({$label})",
                    category: LineItemCategory::Trim,
                    kind: LineItemKind::Labor,
                    quantity: $totalLinearFeet,
                    unit: LineItemUnit::LinearFeet,
                );
            }
        }

        $transitions = (int) ($projectWide['transitions'] ?? 0);
        if ($transitions > 0) {
            $items[] = new LineItemDraft(
                key: 'transitions_material',
                label: 'Transition Strips — materials',
                category: LineItemCategory::Trim,
                kind: LineItemKind::Material,
                quantity: $this->materialWithWaste($transitions, LineItemUnit::Each),
                unit: LineItemUnit::Each,
            );
            $items[] = new LineItemDraft(
                key: 'transitions_labor',
                label: 'Transition Strips — labor',
                category: LineItemCategory::Trim,
                kind: LineItemKind::Labor,
                quantity: $transitions,
                unit: LineItemUnit::Each,
            );
        }
    }

    /**
     * @param  list<LineItemDraft>  $items
     */
    private function emitServiceItems(array &$items, array $projectWide): void
    {
        $doorUndercuts = (int) ($projectWide['door_undercuts'] ?? 0);
        if ($doorUndercuts > 0) {
            $items[] = new LineItemDraft(
                key: 'door_undercuts',
                label: 'Door Undercuts',
                category: LineItemCategory::Services,
                kind: LineItemKind::Labor,
                quantity: $doorUndercuts,
                unit: LineItemUnit::Each,
            );
        }

        $toiletPulls = (int) ($projectWide['toilet_pulls'] ?? 0);
        if ($toiletPulls > 0) {
            $items[] = new LineItemDraft(
                key: 'toilet_pulls',
                label: 'Toilet Pull & Reset',
                category: LineItemCategory::Services,
                kind: LineItemKind::Labor,
                quantity: $toiletPulls,
                unit: LineItemUnit::Each,
            );
        }

        $haulAway = $projectWide['demo_haul_away'] ?? null;
        if ($haulAway !== null && $haulAway !== 'none') {
            $label = self::HAUL_AWAY_LABELS[$haulAway] ?? $haulAway;
            $items[] = new LineItemDraft(
                key: "haul_away_{$haulAway}",
                label: "Demo Haul-Away ({$label})",
                category: LineItemCategory::Services,
                kind: LineItemKind::Labor,
                quantity: 1,
                unit: LineItemUnit::Each,
            );
        }
    }

    /**
     * Bump material quantities for cut waste. Bulk units (sqft, lf) get a
     * flat 10% + round-up to the next multiple of 10 — matches how flooring,
     * baseboards, and quarter round are actually purchased and cut. Each
     * units (transitions) are discrete pieces with no cut waste, so they
     * pass through unchanged.
     */
    private function materialWithWaste(float $quantity, LineItemUnit $unit): float
    {
        if ($quantity <= 0) {
            return 0.0;
        }

        if ($unit === LineItemUnit::Each) {
            return $quantity;
        }

        // Round the intermediate to 6 decimals so ordinary float drift
        // (e.g. 100 * 1.1 / 10 = 11.000000000000002) doesn't push an
        // exact-10 boundary up to the next bucket.
        $tens = round($quantity * 1.1 / 10, 6);

        return (float) (((int) ceil($tens)) * 10);
    }

    /**
     * @return array{rooms: array<string, array<string, string|int>>, project_wide: array<string, string|int>}
     */
    private function normalizeAnswers(Estimate $estimate): array
    {
        $raw = $estimate->getAttribute('interview_answers');

        if ($raw instanceof \ArrayObject) {
            $raw = $raw->getArrayCopy();
        }

        if (! is_array($raw)) {
            $raw = [];
        }

        $rooms = $raw['rooms'] ?? [];
        if ($rooms instanceof \ArrayObject) {
            $rooms = $rooms->getArrayCopy();
        }

        $projectWide = $raw['project_wide'] ?? [];
        if ($projectWide instanceof \ArrayObject) {
            $projectWide = $projectWide->getArrayCopy();
        }

        $cleanRooms = [];
        foreach ((array) $rooms as $roomId => $answers) {
            if ($answers instanceof \ArrayObject) {
                $answers = $answers->getArrayCopy();
            }
            $cleanRooms[(string) $roomId] = (array) $answers;
        }

        return ['rooms' => $cleanRooms, 'project_wide' => (array) $projectWide];
    }
}
