import { Check, Copy, Mail, MailX, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Estimate } from '@/types';

type Props = {
    estimate: Estimate;
};

type CopyState = 'idle' | 'copied' | 'failed';

async function copyTextToClipboard(text: string): Promise<boolean> {
    // Clipboard API is only available in secure contexts (HTTPS/localhost);
    // fall through to the legacy selection method when it's missing or
    // rejects (e.g. permission denied).
    if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(text);

            return true;
        } catch {
            // fall through
        }
    }

    try {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.top = '0';
        textarea.style.left = '0';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(textarea);

        return ok;
    } catch {
        return false;
    }
}

function CopyLinkRow({ label, url }: { label: string; url: string }) {
    const [state, setState] = useState<CopyState>('idle');

    return (
        <div className="space-y-1">
            <label className="text-xs font-medium text-muted-foreground">
                {label}
            </label>
            <div className="flex items-center gap-2">
                <Input
                    value={url}
                    readOnly
                    onFocus={(event) => event.currentTarget.select()}
                    className="h-9 flex-1 font-mono text-xs"
                />
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="h-9 shrink-0"
                    onClick={async () => {
                        const ok = await copyTextToClipboard(url);
                        setState(ok ? 'copied' : 'failed');
                        window.setTimeout(() => setState('idle'), 2000);
                    }}
                >
                    {state === 'copied' && <Check className="mr-2 h-4 w-4" />}
                    {state === 'failed' && <X className="mr-2 h-4 w-4" />}
                    {state === 'idle' && <Copy className="mr-2 h-4 w-4" />}
                    {state === 'copied'
                        ? 'Copied'
                        : state === 'failed'
                          ? 'Copy failed'
                          : 'Copy'}
                </Button>
            </div>
        </div>
    );
}

export default function ApprovalStatusPanel({ estimate }: Props) {
    if (estimate.quote_status !== 'sent') {
        return null;
    }

    const customerEmail = estimate.customer?.email ?? null;
    const approvalUrl = estimate.approval_url;

    return (
        <div className="rounded-xl border bg-muted/30 p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0 space-y-1">
                    <p className="text-sm font-medium">Approval link</p>
                    <p className="text-xs text-muted-foreground">
                        {customerEmail
                            ? `Emailed to ${customerEmail} when the quote was sent.`
                            : 'No email on file — copy the link below to send manually.'}
                    </p>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    {customerEmail ? (
                        <span className="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                            <Mail className="h-3.5 w-3.5" />
                            {customerEmail}
                        </span>
                    ) : (
                        <span className="inline-flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400">
                            <MailX className="h-3.5 w-3.5" />
                            No email on file
                        </span>
                    )}
                </div>
            </div>
            {approvalUrl && (
                <div className="mt-4 border-t pt-3">
                    <CopyLinkRow label="Approval link" url={approvalUrl} />
                </div>
            )}
        </div>
    );
}
