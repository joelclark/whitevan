import { ImageOff } from 'lucide-react';
import { useState } from 'react';
import EstimateInterviewController from '@/actions/App/Http/Controllers/EstimateInterviewController';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import type {
    Estimate,
    EstimateRoom,
    FloorplanAssetsStatus,
    FloorplanPagePreview,
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
    floorplanPages: FloorplanPagePreview[];
};

export default function RoomsList({
    estimate,
    interview,
    floorplanPages,
}: Props) {
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
    const floorplanStatus = estimate.floorplan_assets_status;
    const previewByPage = new Map(floorplanPages.map((p) => [p.page, p]));
    const pendingRoomId = pending?.phase === 'room' ? pending.room_id : null;
    const projectWideActive = pending?.phase === 'project_wide';
    const isComplete = interview?.is_complete ?? false;

    // Rooms-list is sorted by position to match the PHP walker, so a room at
    // index i is "done" when the walker has moved past it: either the whole
    // interview is complete, the walker is now in the project-wide phase, or
    // the walker's current room sits at a later position.
    const doneThroughRoomIndex = (() => {
        if (isComplete || projectWideActive) {
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
                    floorplanStatus={floorplanStatus}
                    floorplanPage={previewByPage.get(room.page) ?? null}
                />
            ))}

            {interview !== null && (
                <ProjectWideCard
                    catalog={interview.catalog.project_wide}
                    answers={answers.project_wide}
                    pendingQuestionKey={projectWideActive ? pending!.key : null}
                    isActive={projectWideActive}
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
    floorplanStatus,
    floorplanPage,
}: {
    room: EstimateRoom;
    roomAnswers: Record<string, string | number>;
    catalog: QuestionShape[];
    pendingQuestionKey: string | null;
    isActive: boolean;
    isDone: boolean;
    action: InterviewAction;
    floorplanStatus: FloorplanAssetsStatus | null;
    floorplanPage: FloorplanPagePreview | null;
}) {
    return (
        <li
            className={`overflow-hidden rounded-xl border bg-card ${
                isActive ? 'border-primary/40 shadow-sm' : ''
            }`}
        >
            <div className="grid gap-0 sm:grid-cols-[minmax(0,14rem)_1fr]">
                <FloorplanThumbnail
                    page={room.page}
                    status={floorplanStatus}
                    preview={floorplanPage}
                />

                <div className="flex flex-col">
                    <div className="flex min-h-14 flex-wrap items-center justify-between gap-3 px-5 py-4">
                        <div className="flex items-center gap-3">
                            <span className="text-base font-medium">
                                {room.name}
                            </span>
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
                </div>
            </div>
        </li>
    );
}

function FloorplanThumbnail({
    page,
    status,
    preview,
}: {
    page: number;
    status: FloorplanAssetsStatus | null;
    preview: FloorplanPagePreview | null;
}) {
    const wrapper =
        'relative aspect-[4/3] w-full overflow-hidden border-b bg-muted/30 sm:aspect-auto sm:h-full sm:border-r sm:border-b-0';

    if (status === 'pending') {
        return (
            <div className={wrapper}>
                <Skeleton className="absolute inset-0 rounded-none" />
            </div>
        );
    }

    if (status === 'ready' && preview) {
        return (
            <a
                href={preview.url}
                target="_blank"
                rel="noreferrer"
                className={`${wrapper} group block`}
                aria-label={`Open floorplan page ${page} in a new tab`}
            >
                <img
                    src={preview.url}
                    width={preview.width || undefined}
                    height={preview.height || undefined}
                    alt={`Floorplan page ${page}`}
                    loading="lazy"
                    className="h-full w-full object-contain transition-transform group-hover:scale-[1.02]"
                />
            </a>
        );
    }

    // ready-but-missing or failed: show a quiet placeholder so the row
    // still has a visual anchor and the layout doesn't shift.
    return (
        <div
            className={`${wrapper} flex items-center justify-center text-muted-foreground`}
        >
            <div className="flex flex-col items-center gap-1 text-xs">
                <ImageOff className="h-6 w-6" />
                <span>
                    {status === 'failed' ? 'Render failed' : 'No preview'}
                </span>
            </div>
        </div>
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
