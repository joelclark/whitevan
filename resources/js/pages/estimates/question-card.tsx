import { Form } from '@inertiajs/react';
import type EstimateInterviewController from '@/actions/App/Http/Controllers/EstimateInterviewController';
import { Button } from '@/components/ui/button';
import type { Estimate, QuestionShape } from '@/types';

export type InterviewAction = ReturnType<
    typeof EstimateInterviewController.store.form
>;

export type NormalizedAnswers = {
    rooms: Record<string, Record<string, string | number>>;
    project_wide: Record<string, string | number>;
};

export function normalizeAnswers(
    raw: Estimate['interview_answers'],
): NormalizedAnswers {
    // Laravel's AsArrayObject cast flattens empty inner maps to [] during
    // serialization. Treat an array as an empty map on the JS side.
    const rooms = Array.isArray(raw?.rooms) ? {} : (raw?.rooms ?? {});
    const project_wide = Array.isArray(raw?.project_wide)
        ? {}
        : (raw?.project_wide ?? {});

    return { rooms, project_wide };
}

export function formatAnswerValue(
    question: QuestionShape,
    value: string | number,
): string {
    if (question.type === 'count') {
        return Number(value) === question.count_max
            ? `${value}+`
            : String(value);
    }

    const str = String(value);

    return question.options[str] ?? str;
}

type QuestionCardProps = {
    question: QuestionShape;
    roomId: number | null;
    action: InterviewAction;
    eyebrow?: string;
    onSubmitted?: () => void;
};

export function QuestionCard({
    question,
    roomId,
    action,
    eyebrow,
    onSubmitted,
}: QuestionCardProps) {
    return (
        <div className="rounded-lg border border-primary/30 bg-primary/5 p-4">
            {eyebrow && (
                <p className="mb-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    {eyebrow}
                </p>
            )}
            <p className="font-semibold">{question.label}</p>
            {question.help && (
                <p className="mt-1 text-sm text-muted-foreground">
                    {question.help}
                </p>
            )}

            <div className="mt-4">
                {question.type === 'select' ? (
                    <OptionButtons
                        question={question}
                        values={Object.keys(question.options)}
                        labelFor={(v) => question.options[v] ?? v}
                        action={action}
                        roomId={roomId}
                        onSubmitted={onSubmitted}
                    />
                ) : (
                    <OptionButtons
                        question={question}
                        values={countRange(question)}
                        labelFor={(v) =>
                            Number(v) === question.count_max
                                ? `${v}+`
                                : String(v)
                        }
                        action={action}
                        roomId={roomId}
                        onSubmitted={onSubmitted}
                    />
                )}
            </div>
        </div>
    );
}

function countRange(q: QuestionShape): string[] {
    const out: string[] = [];

    for (let n = q.count_min; n <= q.count_max; n++) {
        out.push(String(n));
    }

    return out;
}

function OptionButtons({
    question,
    values,
    labelFor,
    action,
    roomId,
    onSubmitted,
}: {
    question: QuestionShape;
    values: string[];
    labelFor: (value: string) => string;
    action: InterviewAction;
    roomId: number | null;
    onSubmitted?: () => void;
}) {
    return (
        <div className="flex flex-wrap gap-2">
            {values.map((value) => (
                <Form
                    key={value}
                    {...action}
                    options={{ preserveScroll: true }}
                    onSuccess={onSubmitted}
                >
                    {({ processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="question_key"
                                value={question.key}
                            />
                            {roomId !== null && (
                                <input
                                    type="hidden"
                                    name="room_id"
                                    value={roomId}
                                />
                            )}
                            <input type="hidden" name="value" value={value} />
                            <Button
                                type="submit"
                                variant="outline"
                                size="sm"
                                disabled={processing}
                            >
                                {labelFor(value)}
                            </Button>
                        </>
                    )}
                </Form>
            ))}
        </div>
    );
}
