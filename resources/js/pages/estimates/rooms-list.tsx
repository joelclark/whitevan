import { useState } from 'react';
import EstimateInterviewController from '@/actions/App/Http/Controllers/EstimateInterviewController';
import { Badge } from '@/components/ui/badge';
import type {
    Estimate,
    EstimateRoom,
    InterviewProps,
    QuestionShape,
} from '@/types';
import type { InterviewAction } from './question-card';
import {
    formatAnswerValue,
    normalizeAnswers,
    QuestionCard,
} from './question-card';

type Props = {
    estimate: Estimate & { rooms: EstimateRoom[] };
    interview: InterviewProps | null;
};

export default function RoomsList({ estimate, interview }: Props) {
    if (estimate.rooms.length === 0 && interview === null) {
        return (
            <div className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                No rooms were extracted from this PDF.
            </div>
        );
    }

    const answers = normalizeAnswers(estimate.interview_answers);
    const action = EstimateInterviewController.store.form(estimate.id);
    const pending = interview?.next_question ?? null;
    const pendingRoomId = pending?.phase === 'room' ? pending.room_id : null;
    const longTailActive = pending?.phase === 'long_tail';
    const isComplete = interview?.is_complete ?? false;

    // Rooms-list is sorted by position to match the PHP walker, so a room at
    // index i is "done" when the walker has moved past it: either the whole
    // interview is complete, the walker is now in long-tail, or the walker's
    // current room sits at a later position.
    const doneThroughRoomIndex = (() => {
        if (isComplete || longTailActive) {
return Infinity;
}

        if (pending?.phase === 'room') {
return pending.room_index - 1;
}

        return -1;
    })();

    return (
        <ul className="space-y-3">
            {estimate.rooms.map((room, index) => (
                <RoomCard
                    key={room.id}
                    room={room}
                    roomAnswers={answers.rooms[String(room.id)] ?? {}}
                    catalog={interview?.catalog.room ?? []}
                    pendingQuestionKey={
                        pendingRoomId === room.id ? pending!.key : null
                    }
                    isActive={pendingRoomId === room.id}
                    isDone={index <= doneThroughRoomIndex}
                    action={action}
                />
            ))}

            {interview !== null && (
                <ProjectWideCard
                    catalog={interview.catalog.long_tail}
                    answers={answers.long_tail}
                    pendingQuestionKey={
                        longTailActive ? pending!.key : null
                    }
                    isActive={longTailActive}
                    isDone={isComplete}
                    action={action}
                />
            )}
        </ul>
    );
}

function RoomCard({
    room,
    roomAnswers,
    catalog,
    pendingQuestionKey,
    isActive,
    isDone,
    action,
}: {
    room: EstimateRoom;
    roomAnswers: Record<string, string | number>;
    catalog: QuestionShape[];
    pendingQuestionKey: string | null;
    isActive: boolean;
    isDone: boolean;
    action: InterviewAction;
}) {
    return (
        <li
            className={`rounded-xl border bg-card ${
                isActive ? 'border-primary/40 shadow-sm' : ''
            }`}
        >
            <div className="flex min-h-14 flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div className="flex items-center gap-3">
                    <span className="text-base font-medium">{room.name}</span>
                    <Badge variant="outline">Page {room.page}</Badge>
                    {isDone && <span aria-label="Complete">✅</span>}
                </div>
                <div className="flex items-center gap-6 text-sm text-muted-foreground">
                    <span>
                        <span className="font-semibold text-foreground">
                            {room.sqft.toLocaleString()}
                        </span>{' '}
                        sq ft
                    </span>
                    <span>
                        <span className="font-semibold text-foreground">
                            {room.linear_feet.toLocaleString()}
                        </span>{' '}
                        linear ft
                    </span>
                </div>
            </div>

            <InterviewCardBody
                catalog={catalog}
                answers={roomAnswers}
                pendingQuestionKey={pendingQuestionKey}
                isActive={isActive}
                roomId={room.id}
                action={action}
            />
        </li>
    );
}

function ProjectWideCard({
    catalog,
    answers,
    pendingQuestionKey,
    isActive,
    isDone,
    action,
}: {
    catalog: QuestionShape[];
    answers: Record<string, string | number>;
    pendingQuestionKey: string | null;
    isActive: boolean;
    isDone: boolean;
    action: InterviewAction;
}) {
    return (
        <li
            className={`rounded-xl border bg-card ${
                isActive ? 'border-primary/40 shadow-sm' : ''
            }`}
        >
            <div className="flex min-h-14 flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div className="flex items-center gap-3">
                    <span className="text-base font-medium">Project-Wide</span>
                    {isDone && <span aria-label="Complete">✅</span>}
                </div>
            </div>

            <InterviewCardBody
                catalog={catalog}
                answers={answers}
                pendingQuestionKey={pendingQuestionKey}
                isActive={isActive}
                roomId={null}
                action={action}
            />
        </li>
    );
}

function InterviewCardBody({
    catalog,
    answers,
    pendingQuestionKey,
    isActive,
    roomId,
    action,
}: {
    catalog: QuestionShape[];
    answers: Record<string, string | number>;
    pendingQuestionKey: string | null;
    isActive: boolean;
    roomId: number | null;
    action: InterviewAction;
}) {
    const [editingKey, setEditingKey] = useState<string | null>(null);

    const questionByKey = new Map(catalog.map((q) => [q.key, q]));
    const activeKey = editingKey ?? pendingQuestionKey;
    const activeQuestion = activeKey ? questionByKey.get(activeKey) : null;

    const answeredEntries = catalog
        .map((q) => [q, answers[q.key]] as const)
        .filter(([, value]) => value !== undefined);

    if (answeredEntries.length === 0 && !activeQuestion) {
        return null;
    }

    return (
        <div className="space-y-3 border-t px-5 py-4">
            {answeredEntries.length > 0 && (
                <dl className="divide-y">
                    {answeredEntries.map(([question, value]) => {
                        const isEditingThis = editingKey === question.key;

                        return (
                            <button
                                key={question.key}
                                type="button"
                                onClick={() =>
                                    setEditingKey(
                                        isEditingThis ? null : question.key,
                                    )
                                }
                                className={`flex w-full items-center justify-between gap-3 py-2 text-left text-sm transition-colors hover:text-foreground ${
                                    isEditingThis
                                        ? 'text-foreground'
                                        : 'text-muted-foreground'
                                }`}
                            >
                                <dt>{question.label}</dt>
                                <dd className="font-medium text-foreground">
                                    {formatAnswerValue(question, value)}
                                </dd>
                            </button>
                        );
                    })}
                </dl>
            )}

            {activeQuestion && (
                <QuestionCard
                    question={activeQuestion}
                    roomId={roomId}
                    action={action}
                    eyebrow={
                        editingKey
                            ? 'Editing'
                            : isActive
                              ? 'Next question'
                              : undefined
                    }
                    onSubmitted={() => setEditingKey(null)}
                />
            )}
        </div>
    );
}
