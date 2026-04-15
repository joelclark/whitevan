import { Badge } from '@/components/ui/badge';
import type { EstimateRoom } from '@/types';

type Props = {
    rooms: EstimateRoom[];
};

export default function RoomsList({ rooms }: Props) {
    if (rooms.length === 0) {
        return (
            <div className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                No rooms were extracted from this PDF.
            </div>
        );
    }

    return (
        <ul className="space-y-2">
            {rooms.map((room) => (
                <li
                    key={room.id}
                    className="flex min-h-14 flex-wrap items-center justify-between gap-3 rounded-xl border bg-card px-5 py-4"
                >
                    <div className="flex items-center gap-3">
                        <span className="text-base font-medium">
                            {room.name}
                        </span>
                        <Badge variant="outline">Page {room.page}</Badge>
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
                </li>
            ))}
        </ul>
    );
}
