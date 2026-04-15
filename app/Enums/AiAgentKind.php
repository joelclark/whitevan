<?php

namespace App\Enums;

enum AiAgentKind: string
{
    case FloorPlanExtraction = 'floor_plan_extraction';

    public function label(): string
    {
        return match ($this) {
            self::FloorPlanExtraction => 'Floor Plan Extraction',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FloorPlanExtraction => 'Reads a customer-uploaded floor plan PDF and extracts room-level square footage and perimeter.',
        };
    }

    public function defaultSystemPrompt(): string
    {
        return match ($this) {
            self::FloorPlanExtraction => <<<'PROMPT'
                You are an assistant that reads floor plan PDFs produced by a field measuring tool and
                extracts a structured summary.

                - Title should be the most top / prominent text on the first page.
                - Identify every distinct room.
                - Record the room name exactly as labelled on the drawing.
                - Record the room's floor area in square feet.
                - Record the room's perimeter in linear feet when it is clearly indicated.

                Round all values up, no decimals.

                Only include entries in `errors` when something prevents you from producing a confident
                answer — for example, an unreadable page or a missing measurement. Do not include
                stylistic warnings.
                PROMPT,
        };
    }
}
