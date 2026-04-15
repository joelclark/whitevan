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
        <>
            <Heading title={title} description={description} />

            <div className="flex-1">{children}</div>
        </>
    );
}
