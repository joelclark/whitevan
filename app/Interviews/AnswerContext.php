<?php

namespace App\Interviews;

final readonly class AnswerContext
{
    /**
     * @param  array<string, array<string, string|int>>  $rooms  Room answers keyed by stringified room id.
     * @param  array<string, string|int>  $longTail
     */
    public function __construct(
        public array $rooms,
        public array $longTail,
        public ?int $currentRoomId,
        public Phase $phase,
    ) {}

    /**
     * @return array<string, string|int>
     */
    public function roomAnswers(?int $roomId = null): array
    {
        $id = $roomId ?? $this->currentRoomId;

        if ($id === null) {
            return [];
        }

        return $this->rooms[(string) $id] ?? [];
    }
}
