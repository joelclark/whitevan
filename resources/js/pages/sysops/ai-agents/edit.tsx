import { Transition } from '@headlessui/react';
import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AiAgentController from '@/actions/App/Http/Controllers/Sysops/AiAgentController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { dashboard as sysopsDashboard } from '@/routes/sysops';
import { index as sysopsAiAgentsIndex } from '@/routes/sysops/ai-agents';
import type { AiAgentSetting } from '@/types';

type Props = {
    agent: AiAgentSetting;
    responseFormat: string;
};

export default function SysopsAiAgentEdit({ agent, responseFormat }: Props) {
    setLayoutProps({
        title: agent.label,
        description: agent.description ?? 'AI agent configuration',
    });

    return (
        <>
            <Head title={`${agent.label} — AI Agents`} />

            <div>
                <div className="mb-4">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={sysopsAiAgentsIndex()}>
                            <ArrowLeft className="mr-1 h-4 w-4" />
                            Back to AI agents
                        </Link>
                    </Button>
                </div>

                <div className="max-w-3xl space-y-6">
                    <Form
                        {...AiAgentController.update.form(agent.id)}
                        options={{ preserveScroll: true }}
                        className="space-y-6"
                    >
                        {({ processing, recentlySuccessful, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor="description"
                                        className="text-base"
                                    >
                                        Description
                                    </Label>
                                    <Textarea
                                        id="description"
                                        name="description"
                                        defaultValue={agent.description ?? ''}
                                        rows={2}
                                        maxLength={2000}
                                    />
                                    <InputError message={errors.description} />
                                </div>

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor="system_prompt"
                                        className="text-base"
                                    >
                                        System prompt
                                    </Label>
                                    <p className="text-sm text-muted-foreground">
                                        This text is sent to the model on every
                                        run. The JSON response format shown
                                        below is appended automatically — you
                                        don&apos;t need to repeat it here.
                                    </p>
                                    <Textarea
                                        id="system_prompt"
                                        name="system_prompt"
                                        defaultValue={agent.system_prompt}
                                        rows={18}
                                        maxLength={20000}
                                        className="font-mono text-sm"
                                        required
                                    />
                                    <InputError
                                        message={errors.system_prompt}
                                    />
                                </div>

                                <div className="flex items-center gap-4">
                                    <Button
                                        disabled={processing}
                                        className="h-12 px-5 text-base"
                                    >
                                        Save changes
                                    </Button>
                                    <Transition
                                        show={recentlySuccessful}
                                        enter="transition ease-in-out"
                                        enterFrom="opacity-0"
                                        leave="transition ease-in-out"
                                        leaveTo="opacity-0"
                                    >
                                        <p className="text-sm text-muted-foreground">
                                            Saved
                                        </p>
                                    </Transition>
                                </div>
                            </>
                        )}
                    </Form>

                    <section className="rounded-xl border bg-muted/20 p-5">
                        <h2 className="mb-2 text-sm font-semibold">
                            Required response format (appended to every prompt)
                        </h2>
                        <pre className="overflow-x-auto rounded-md bg-background p-4 font-mono text-xs text-muted-foreground">
                            {responseFormat}
                        </pre>
                    </section>
                </div>
            </div>
        </>
    );
}

SysopsAiAgentEdit.layout = {
    breadcrumbs: [
        { title: 'Sysops', href: sysopsDashboard().url },
        { title: 'AI Agents', href: sysopsAiAgentsIndex().url },
    ],
};
