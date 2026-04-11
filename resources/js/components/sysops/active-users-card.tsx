import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { formatCompactNumber } from '@/lib/format-number';

type Point = {
    date: string;
    count: number;
};

const DAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const chartConfig = {
    count: {
        label: 'Active users',
        color: 'var(--color-blue-500)',
    },
} satisfies ChartConfig;

function dayLabel(date: string): string {
    const parsed = new Date(`${date}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) {
        return '';
    }

    return DAY_LABELS[parsed.getDay()];
}

export function ActiveUsersCard({ data }: { data: Point[] }) {
    const latest = data[data.length - 1]?.count ?? 0;

    const chartData = data.map((point) => ({
        ...point,
        day: dayLabel(point.date),
    }));

    return (
        <div className="relative flex aspect-video flex-col gap-3 overflow-hidden rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <div className="flex items-start justify-between">
                <div>
                    <div className="text-sm font-medium text-foreground">
                        Active users
                    </div>
                    <div className="text-xs text-muted-foreground">
                        7-day rolling
                    </div>
                </div>
                <div className="text-right">
                    <div className="text-2xl font-semibold tabular-nums">
                        {formatCompactNumber(latest)}
                    </div>
                    <div className="text-xs text-muted-foreground">today</div>
                </div>
            </div>

            <ChartContainer config={chartConfig} className="min-h-0 flex-1">
                <BarChart
                    accessibilityLayer
                    data={chartData}
                    margin={{ top: 4, right: 4, left: 0, bottom: 0 }}
                >
                    <CartesianGrid vertical={false} />
                    <XAxis
                        dataKey="day"
                        tickLine={false}
                        axisLine={false}
                        tickMargin={6}
                        fontSize={10}
                    />
                    <YAxis
                        tickLine={false}
                        axisLine={false}
                        width={28}
                        fontSize={10}
                        tickFormatter={formatCompactNumber}
                        allowDecimals={false}
                    />
                    <ChartTooltip
                        cursor={false}
                        content={
                            <ChartTooltipContent
                                labelFormatter={(_, payload) =>
                                    payload?.[0]?.payload?.date ?? ''
                                }
                                formatter={(value) =>
                                    formatCompactNumber(Number(value))
                                }
                            />
                        }
                    />
                    <Bar
                        dataKey="count"
                        fill="var(--color-count)"
                        radius={[4, 4, 0, 0]}
                    />
                </BarChart>
            </ChartContainer>
        </div>
    );
}
