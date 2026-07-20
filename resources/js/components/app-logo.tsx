import { usePage } from '@inertiajs/react';

export default function AppLogo() {
    const { auth } = usePage().props;

    return (
        <div className="grid flex-1 text-left text-sm">
            <span className="truncate leading-tight font-semibold">
                {auth.account?.name ?? 'whitevan.app'}
            </span>
        </div>
    );
}
