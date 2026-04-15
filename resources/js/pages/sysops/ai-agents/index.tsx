import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Bot, ChevronRight } from 'lucide-react';
import { dashboard as sysopsDashboard } from '@/routes/sysops';
import {
    edit as sysopsAiAgentEdit,
    index as sysopsAiAgentsIndex,
} from '@/routes/sysops/ai-agents';
import type { AiAgentSetting } from '@/types';

type Props = {
    agents: Pick<
        AiAgentSetting,
        'id' | 'kind' | 'label' | 'description' | 'updated_at'
    >[];
};

export default function SysopsAiAgentsIndex({ agents }: Props) {
    setLayoutProps({
        title: 'AI Agents',
        description:
            'System prompts that drive the AI agents used across the app.',
    });

    return (
        <>
            <Head title="AI Agents" />

            <div className="mt-6 space-y-3">
                {agents.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                        No AI agents are configured yet.
                    </div>
                ) : (
                    agents.map((agent) => (
                        <Link
                            key={agent.id}
                            href={sysopsAiAgentEdit(agent.id)}
                            className="flex min-h-16 items-center justify-between gap-4 rounded-xl border bg-card p-5 transition-colors hover:bg-muted/40"
                        >
                            <div className="flex min-w-0 items-start gap-4">
                                <div className="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <Bot className="h-5 w-5" />
                                </div>
                                <div className="min-w-0">
                                    <p className="text-base font-semibold">
                                        {agent.label}
                                    </p>
                                    {agent.description && (
                                        <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                                            {agent.description}
                                        </p>
                                    )}
                                </div>
                            </div>
                            <ChevronRight className="h-5 w-5 flex-shrink-0 text-muted-foreground" />
                        </Link>
                    ))
                )}
            </div>
        </>
    );
}

SysopsAiAgentsIndex.layout = {
    breadcrumbs: [
        { title: 'Sysops', href: sysopsDashboard().url },
        { title: 'AI Agents', href: sysopsAiAgentsIndex().url },
    ],
};
