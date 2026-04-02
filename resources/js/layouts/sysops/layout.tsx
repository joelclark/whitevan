import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';

export default function SysopsLayout({ children }: PropsWithChildren) {
    return (
        <div className="px-4 py-6">
            <Heading title="System Operations" description="Administer accounts and system-wide settings" />

            <div className="flex-1">{children}</div>
        </div>
    );
}
