import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';

type SysopsLayoutProps = PropsWithChildren<{
    title?: string;
    description?: string;
}>;

export default function SysopsLayout({
    children,
    title = 'System Operations',
    description = 'Administer accounts and system-wide settings',
}: SysopsLayoutProps) {
    return (
        <div className="px-4 py-6">
            <Heading title={title} description={description} />

            <div className="flex-1">{children}</div>
        </div>
    );
}
