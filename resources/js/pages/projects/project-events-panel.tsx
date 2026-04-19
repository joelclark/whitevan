import { Link } from '@inertiajs/react';
import { edit as estimatesEdit } from '@/routes/estimates';
import type { ProjectEventListItem } from '@/types';

type Props = {
    events: ProjectEventListItem[];
};

function formatDateTime(value: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function actorLabel(event: ProjectEventListItem): string {
    if (event.actor_name) {
        return event.actor_name;
    }

    if (event.actor_type === 'customer') {
        return 'Customer';
    }

    return 'System';
}

export default function ProjectEventsPanel({ events }: Props) {
    return (
        <div>
            <h2 className="mb-4 text-lg font-semibold">History</h2>

            {events.length === 0 ? (
                <div className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                    No activity yet.
                </div>
            ) : (
                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left">
                                <th className="px-4 py-3 font-medium">Date</th>
                                <th className="px-4 py-3 font-medium">Event</th>
                                <th className="px-4 py-3 font-medium">
                                    Estimate
                                </th>
                                <th className="px-4 py-3 font-medium">Actor</th>
                            </tr>
                        </thead>
                        <tbody>
                            {events.map((event) => (
                                <tr
                                    key={event.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                        {formatDateTime(event.created_at)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {event.event_label}
                                    </td>
                                    <td className="px-4 py-3">
                                        {event.estimate_id === null ? (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        ) : event.estimate_deleted ? (
                                            <span className="text-muted-foreground">
                                                {event.estimate_title ?? '—'}
                                            </span>
                                        ) : (
                                            <Link
                                                href={estimatesEdit(
                                                    event.estimate_id,
                                                )}
                                                className="hover:underline"
                                            >
                                                {event.estimate_title ?? '—'}
                                            </Link>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {actorLabel(event)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
