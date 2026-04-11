import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { stop as impersonateStop } from '@/routes/impersonate';
import type { Auth } from '@/types';

export function ImpersonationBanner() {
    const { auth } = usePage<{ auth: Auth }>().props;

    if (!auth.impersonating) {
        return null;
    }

    function stop() {
        router.delete(impersonateStop().url);
    }

    return (
        <div className="flex items-center justify-between gap-3 bg-amber-500 px-4 py-2 text-sm text-amber-950 dark:bg-amber-400">
            <span>
                Impersonating <strong>{auth.impersonating.account.name}</strong>{' '}
                as admin.
            </span>
            <Button
                variant="outline"
                size="sm"
                onClick={stop}
                className="border-amber-900/30 bg-amber-100 text-amber-950 hover:bg-amber-50"
            >
                Stop impersonating
            </Button>
        </div>
    );
}
