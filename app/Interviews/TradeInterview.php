<?php

namespace App\Interviews;

use App\Models\Estimate;
use Illuminate\Validation\ValidationException;

interface TradeInterview
{
    public function nextQuestionFor(Estimate $estimate): ?PendingQuestion;

    /**
     * Writes a validated answer into $estimate->interview_answers in memory.
     * Does not call save(). Clears any orphan answers whose conditional
     * predicate no longer holds.
     *
     * @throws ValidationException
     */
    public function recordAnswerFor(
        Estimate $estimate,
        string $questionKey,
        ?int $roomId,
        mixed $rawValue,
    ): void;

    public function isComplete(Estimate $estimate): bool;

    /**
     * Flat catalog of every question this trade can ask, grouped by phase.
     * Used by the review panel to render editable rows for any answered
     * question without re-walking the interview.
     *
     * @return array{room: list<array<string, mixed>>, project_wide: list<array<string, mixed>>}
     */
    public function catalog(): array;
}
