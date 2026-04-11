import { Head, setLayoutProps } from '@inertiajs/react';
import { ActiveUsersCard } from '@/components/sysops/active-users-card';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { dashboard as sysopsDashboard } from '@/routes/sysops';

type ActiveUserPoint = {
    date: string;
    count: number;
};

export default function SysopsDashboard({
    activeUserSeries,
}: {
    activeUserSeries: ActiveUserPoint[];
}) {
    setLayoutProps({
        title: 'Dashboard',
        description: 'System operator overview',
    });

    return (
        <>
            <Head title="Sysops Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                    <ActiveUsersCard data={activeUserSeries} />
                </div>
            </div>
        </>
    );
}

SysopsDashboard.layout = {
    breadcrumbs: [
        { title: 'Sysops', href: sysopsDashboard().url },
        { title: 'Dashboard', href: sysopsDashboard().url },
    ],
};
