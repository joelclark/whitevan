<?php

namespace App\Interviews;

use App\Enums\LineItemCategory;
use App\Enums\LineItemKind;
use App\Enums\LineItemUnit;

final readonly class LineItemDraft
{
    public function __construct(
        public string $key,
        public string $label,
        public LineItemCategory $category,
        public LineItemKind $kind,
        public float $quantity,
        public LineItemUnit $unit,
        public ?string $notes = null,
    ) {}
}
