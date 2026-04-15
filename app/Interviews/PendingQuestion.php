<?php

namespace App\Interviews;

use App\Models\EstimateRoom;

final readonly class PendingQuestion
{
    public function __construct(
        public Question $question,
        public Phase $phase,
        public ?EstimateRoom $room,
        public int $roomIndex,
        public int $totalRooms,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->question->key(),
            'label' => $this->question->label(),
            'help' => $this->question->help(),
            'type' => $this->question->type()->value,
            'options' => $this->question->options(),
            'count_min' => $this->question->countMin(),
            'count_max' => $this->question->countMax(),
            'phase' => $this->phase->value,
            'room_id' => $this->room?->id,
            'room_name' => $this->room?->name,
            'room_index' => $this->roomIndex,
            'total_rooms' => $this->totalRooms,
        ];
    }
}
